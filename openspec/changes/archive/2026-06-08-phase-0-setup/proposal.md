# Proposal: Phase 0 — Project Setup (v3)

## Intent

Boot a two-service Laravel 13 monorepo with Docker Compose, PostgreSQL 18, LocalStack 4.14, and AWS SAM IaC. Add upload frontend with pre-signed S3 URLs. Enforce English-only naming across all technical artifacts as non-negotiable project convention.

## Scope

### In Scope
- Git init + `.gitignore`
- Monorepo: `importer-service/`, `query-service/`, `infrastructure/`
- `docker-compose.yml`: importer-db, query-db, localstack (S3+SQS), both app services
- Laravel 13 scaffold + Clean Architecture dirs in both services
- `.env.example` (root + per-service)
- `infrastructure/template.yaml`: ECS Fargate (4 vCPU, 8GB RAM), S3, SQS, API Gateway, IAM roles
- File upload docs: pre-signed URL flow (prod) + local path fallback (dev)
- Upload frontend: file picker + upload button + progress/status, pre-signed S3 URLs, LocalStack + prod compatible
- English-only naming convention: documented as non-negotiable project standard

### Out of Scope
- Business code, migrations, app Dockerfiles, CI/CD, LocalStack setup (Phase 5)
- File processing code
- Frontend auth, styling polish, chunked upload UI (deferred)

## Capabilities

### New Capabilities
- `development-environment`: Docker Compose with PostgreSQL per service, LocalStack 4.14 (S3+SQS), services on ports 8000/8001
- `project-scaffolding`: Laravel 13 monorepo with Clean Architecture layers
- `aws-infrastructure`: SAM template — ECS Fargate, S3, SQS, API Gateway, IAM roles
- `file-ingestion`: Pre-signed URL upload flow (+ local path fallback), `.env` toggle
- `file-upload-frontend`: Browser-based BCRA file upload with pre-signed S3 URLs, progress/status, LocalStack dev + AWS prod

### Modified Capabilities
None.

## English-Only Naming Convention (MANDATORY)

**All** technical elements MUST use English. No exceptions.

| Category | Rule | Example ✅ | Rejected ❌ |
|----------|------|-----------|------------|
| DB tables/columns | English nouns | `debtors`, `identification_number` | `deudores`, `nro_identificacion` |
| API endpoints | English paths | `/debtors/{cuit}` | `/deudores/{cuit}` |
| Domain events | English PascalCase | `DebtorProcessed` | `DeudorProcesado` |
| Code identifiers | English | `$maxSituation`, `DebtorController` | `$situacionMaxima`, `DeudorController` |
| Config keys | English | `s3.bucket_name` | `s3.nombre_bucket` |
| Logs, errors, comments, commits | English only | — | — |

**Key mappings**: Deudor→Debtor, Entidad→Entity, Situación→Situation, Préstamos→Loans, Monto→Amount, Código→Code, Identificación→Identification, Fecha→Date, Actividad→Activity, Garantía→Guarantee, Previsiones→Provisions, DíasAtraso→DaysOverdue.

Convention doc lives at `docs/conventions/naming.md`. All code reviews MUST reject Spanish identifiers.

## Approach

1. `git init` → `.gitignore`
2. `docker-compose.yml` with `query-service` (renamed)
3. `composer create-project laravel/laravel:^13.0` for both services
4. Clean Architecture dirs: `app/Domain/`, `app/Application/`, `app/Infrastructure/`
5. `infrastructure/template.yaml` — SAM resources (ECS, S3, SQS, API Gateway, IAM)
6. `.env.example` files: root + both services + infrastructure vars
7. Frontend: static HTML+JS served by importer-service or nginx; pre-signed URL flow
8. Document naming convention in `docs/conventions/naming.md`
9. Verify `docker-compose up -d`, `sam validate --lint`

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `/` | Modified | `.gitignore`, `docker-compose.yml`, `.env.example` |
| `importer-service/` | New | Laravel 13 + Clean Architecture + upload frontend |
| `query-service/` | New (renamed) | Laravel 13 + Clean Architecture |
| `infrastructure/` | New | SAM template, AWS configs |
| `docs/architecture/` | Modified | Rename references, upload flow docs |
| `docs/conventions/` | New | English-only naming convention |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| SAM template validation fails | Med | `sam validate --lint` before commit |
| Port conflicts | Low | Explicit port mapping |
| ECS cold start for 6GB files | Low | 8GB RAM; async processing |
| LocalStack pre-signed URLs mismatch prod | Low | `.env`-based S3 endpoint override; test both |
| Spanish identifiers in existing docs | Low | Audit docs/ in this phase; code not written yet |

## Rollback Plan

`rm -rf importer-service query-service infrastructure .git docker-compose.yml .env.example docs/conventions/`. Full reset.

## Dependencies

- Docker + Compose v2, PHP 8.5 CLI + Composer, Git, AWS SAM CLI
- Internet (image pulls, composer)

## Success Criteria

- [ ] `docker-compose up -d` — all infra services healthy
- [ ] Both `artisan` files executable (Laravel 13)
- [ ] `.env.example` at root and both services
- [ ] Clean Architecture dirs in both `app/`
- [ ] `sam validate --lint` passes
- [ ] Docs: `query-api` → `query-service`, pre-signed URL flow, naming convention
- [ ] Frontend: file picker renders, upload triggers pre-signed URL flow (LocalStack)
- [ ] Naming convention documented; `grep -r "deudor\|entidad\|situacion\|préstamo" --include="*.php"` returns zero hits
