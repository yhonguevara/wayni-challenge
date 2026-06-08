# Design: Phase 0 — Project Setup

## Technical Approach

Bootstrap a monorepo with two independent Laravel 13 services (`importer-service`, `query-service`), Docker Compose for local dev (PostgreSQL 18 per-service + LocalStack 4.14), AWS SAM IaC for production, and a minimal upload frontend. No business code — only scaffolding, infrastructure, and conventions.

This design follows the proposal's approach and satisfies requirements from three delta specs: `development-environment`, `project-scaffolding`, and `aws-infrastructure`.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| Service naming | `query-api` (existing docs) vs `query-service` (spec) | Spec mandates `query-service`; existing docs updated later | **`query-service`** |
| Docker base image | `php:8.5-cli-alpine` vs `php:8.5-fpm` + nginx | Alpine CLI is simpler for `artisan serve`; Fargate uses same image | **`php:8.5-cli-alpine`** (deferred — Dockerfiles out of scope) |
| Frontend tech | React SPA vs static HTML+Alpine.js vs Blade template | Alpine.js adds reactivity without build step; Blade keeps it in Laravel | **Blade + Alpine.js** in importer-service |
| Frontend serving | Separate nginx container vs Laravel route | Extra container adds complexity for a single page | **Laravel route** (`GET /upload` returns Blade view) |
| LocalStack init | Init container vs artisan command vs manual | Proposal defers to Phase 5; compose just starts LocalStack | **Deferred** — compose starts service only |
| SAM entry point | ALB vs API Gateway HTTP API | ALB cheaper for ECS; API Gateway simpler for REST | **API Gateway HTTP API** (per spec REQ-AWS-005) |
| Clean Arch layers | 3 layers (Domain/App/Infra) vs 4 (+Presentation) | Spec REQ-SCAF-003 requires 3; Laravel's `Http/` serves as Presentation | **3 layers** + Laravel defaults |
| Laravel version pinning | `^13.0` vs exact `13.x.y` | `^13.0` allows patches; exact pin too rigid for scaffold | **`^13.0`** in composer.json |

## Data Flow

### File Upload Flow (Pre-signed URL)

```
Browser ──GET /upload──→ importer-service (Blade page)
   │
   ├──POST /api/presign──→ importer-service
   │     └──→ S3::createPresignedPost() ──→ { url, fields }
   │
   ├──POST {url} (multipart)──→ S3/LocalStack (direct upload)
   │
   └──POST /api/notify-upload──→ importer-service
         └──→ dispatch ECS task / queue job (Phase 1+)
```

Dev fallback: `FILE_SOURCE=local` env var → `POST /process` with local path.

### LocalStack Compatibility

Pre-signed URLs from LocalStack return `http://localhost:4566/...` or `http://s3.localhost.localstack.cloud:4566/...`. The frontend MUST use the URL as-is (no host rewriting). Laravel config:

```
AWS_URL=http://localhost:4566          # browser-facing
AWS_ENDPOINT=http://localstack:4566    # server-to-server (Docker network)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `.gitignore` | Create | Root gitignore: vendor/, .env, node_modules/, IDE files |
| `.env.example` | Create | Root env: Compose project name, AWS creds, region |
| `docker-compose.yml` | Create | 5 services: importer-service, query-service, importer-db, query-db, localstack |
| `importer-service/` | Create | Laravel 13 scaffold via `composer create-project` |
| `importer-service/app/Domain/` | Create | Entities/, ValueObjects/, Events/, Repositories/ |
| `importer-service/app/Application/` | Create | UseCases/, DTOs/, Ports/ |
| `importer-service/app/Infrastructure/` | Create | Empty layer for concrete implementations |
| `importer-service/.env.example` | Create | DB, SQS, S3, notification config |
| `importer-service/resources/views/upload.blade.php` | Create | Upload frontend (Alpine.js) |
| `importer-service/routes/web.php` | Modify | Add `GET /upload` route |
| `importer-service/routes/api.php` | Modify | Add `POST /api/presign`, `POST /api/notify-upload` stubs |
| `query-service/` | Create | Laravel 13 scaffold via `composer create-project` |
| `query-service/app/Domain/` | Create | Same Clean Architecture layout |
| `query-service/app/Application/` | Create | Same Clean Architecture layout |
| `query-service/app/Infrastructure/` | Create | Empty layer |
| `query-service/.env.example` | Create | DB, SQS consumer config |
| `infrastructure/template.yaml` | Create | SAM template: ECS, S3, SQS, API Gateway, IAM, CloudWatch |
| `infrastructure/samconfig.toml` | Create | SAM deploy defaults |
| `docs/conventions/naming.md` | Create | English-only naming convention (mandatory) |

## Interfaces / Contracts

### Docker Compose Services

```yaml
services:
  importer-db:     # postgres:18-alpine, port 5432, healthcheck
  query-db:        # postgres:18-alpine, port 5433, healthcheck
  localstack:      # localstack/localstack:4.14, port 4566, SERVICES=s3,sqs
  importer-service: # build: ./importer-service, port 8001:8000
  query-service:    # build: ./query-service, port 8000:8000
```

### SAM Template Resources

| Resource | Type | Key Config |
|----------|------|------------|
| `BcraFilesBucket` | `AWS::S3::Bucket` | Versioning enabled, SSE-S3 encryption, CORS for pre-signed URLs |
| `DebtorEventsQueue` | `AWS::SQS::Queue` | VisibilityTimeout 300s, DLQ with maxReceiveCount=3 |
| `EntityEventsQueue` | `AWS::SQS::Queue` | Same config as debtor queue |
| `ImporterTaskDef` | `AWS::ECS::TaskDefinition` | Fargate, 4096 CPU, 8192 Memory, awsvpc |
| `QueryTaskDef` | `AWS::ECS::TaskDefinition` | Same as importer |
| `QueryHttpApi` | `AWS::Serverless::HttpApi` | Routes to query ECS service |
| `EcsTaskExecutionRole` | `AWS::IAM::Role` | ECR pull + CloudWatch logs |
| `EcsTaskRole` | `AWS::IAM::Role` | S3 read/write + SQS send/receive |

### Pre-signed URL Contract

```
POST /api/presign
Request:  { "filename": "deudores.txt" }
Response: { "upload_url": "http://...", "fields": { "key": "...", "policy": "..." } }

POST /api/notify-upload
Request:  { "key": "uploads/deudores.txt", "size": 12345 }
Response: { "message": "File queued for processing", "import_id": "uuid" }
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Smoke | `docker-compose up -d` succeeds, all 5 services healthy | Manual verification |
| Smoke | `php artisan --version` in both services reports Laravel 13 | Manual verification |
| Validation | `sam validate --lint` passes | SAM CLI |
| Convention | `grep -r "deudor\|entidad\|situacion\|prestamo" --include="*.php"` returns zero | Shell command |
| Functional | Upload page renders at `http://localhost:8001/upload` | Browser check |

No PHPUnit tests in Phase 0 — no business logic exists yet.

## Migration / Rollout

No migration required. This is a greenfield scaffold.

Rollback: `rm -rf importer-service query-service infrastructure .git docker-compose.yml .env.example docs/conventions/`

## Open Questions

- [ ] `file-ingestion` and `file-upload-frontend` specs not yet written — only 3 of 5 capabilities have delta specs. Design covers these based on proposal intent; specs should be added before apply phase.
- [ ] App Dockerfiles are out of scope per proposal — `docker-compose.yml` `build:` directives will reference directories without Dockerfiles. Services won't start until Phase 1 adds them. Consider adding placeholder Dockerfiles in this phase.
- [ ] Existing `docs/architecture/` uses Spanish naming (`deudores`, `entidades`) — audit and update deferred to a later phase, but `docs/conventions/naming.md` MUST document the migration plan.
