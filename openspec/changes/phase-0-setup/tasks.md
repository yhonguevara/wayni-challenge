# Tasks: Phase 0 — Project Setup

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~715 (human-authored) + ~5000 (Laravel boilerplate in ~200 files) |
| 400-line budget risk | High |
| Chained PRs recommended | No |
| Suggested split | Single batch (atomic commits, no PRs) |
| Delivery strategy | single-pr |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: High

**Note:** User explicitly set review budget to 800 lines and specified atomic commits without PRs — size-exception pre-approved. High risk flags generated boilerplate volume, not human-authored content.

## Phase 1: Foundation & Scaffolding (parallel-safe)

- [x] 1.1 `git init` + create root `.gitignore` (vendor/, .env, node_modules/, *.log, .idea/, .vscode/, docker-compose.override.yml) — ~25 lines
- [x] 1.2 Create root `.env.example` (COMPOSE_PROJECT_NAME, AWS_DEFAULT_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY) — ~10 lines
- [x] 1.3 `composer create-project laravel/laravel:^13.0` for `importer-service/` — generated ~100 files
- [x] 1.4 `composer create-project laravel/laravel:^13.0` for `query-service/` — generated ~100 files
- [x] 1.5 Add Clean Architecture dirs in both services: `app/Domain/{Entities,ValueObjects,Events,Repositories}/`, `app/Application/{UseCases,DTOs,Ports}/`, `app/Infrastructure/` with `.gitkeep` — 14 dirs
- [x] 1.6 Create service `.env.example` files: `importer-service/.env.example` (DB, S3, SQS, notification config) + `query-service/.env.example` (DB, SQS consumer config) — ~75 lines

## Phase 2: Infrastructure (depends on service structure)

- [x] 2.1 Create `docker-compose.yml` — 5 services (importer-db:5432, query-db:5433, localstack:4566, importer-service:8001, query-service:8000), named volumes, healthchecks — ~100 lines
- [x] 2.2 Create `infrastructure/template.yaml` — ECS Fargate 4096/8192, S3 bucket (versioning+SSE), SQS queues (DLQ), API Gateway HTTP API, IAM roles, CloudWatch log groups, Environment param — ~250 lines
- [x] 2.3 Create `infrastructure/samconfig.toml` — SAM deploy defaults — ~25 lines

## Phase 3: Upload Frontend (depends on importer-service)

- [x] 3.1 Create `importer-service/resources/views/upload.blade.php` — Alpine.js file picker, upload button with disabled state, XMLHttpRequest pre-signed URL flow, progress bar, error+retry — ~80 lines
- [x] 3.2 Add stubs in `importer-service/routes/web.php` (`GET /upload`) and `routes/api.php` (`POST /api/presign`, `POST /api/notify-upload`) — ~5 lines
- [x] 3.3 Add `config/s3.php` with `AWS_URL` (browser-facing), `AWS_ENDPOINT` (Docker network) for LocalStack pre-signed URL compatibility — ~15 lines

## Phase 4: Documentation Sync (independent, parallel-safe)

- [x] 4.1 Create `docs/conventions/naming.md` — key mappings table (Deudor→Debtor, Entidad→Entity, etc.), enforcement rules for code reviews — ~60 lines
- [x] 4.2 Update `docs/architecture/services.md` — rename `Query API` → `Query Service`, fix `query-api` → `query-service`, add "Last updated: Phase 0 SDD" — ~15 lines changed
- [x] 4.3 Update `docs/architecture/infrastructure.md` — add SAM template section, monorepo structure (`infrastructure/`), remove `query-api` references → `query-service`, add "Last updated: Phase 0 SDD" — ~40 lines changed
- [x] 4.4 Update `docs/architecture/api-contracts.md` — rename endpoints: `/deudores` → `/debtors`, `/entidades` → `/entities`, response fields to English (nro_identificacion→identificationNumber, situacion_maxima→maxSituation, codigo_entidad→entityCode, suma_total_prestamos→totalLoanAmount), add "Last updated: Phase 0 SDD" — ~30 lines changed

## Phase 5: Verification

- [x] 5.1 `docker-compose config` validates without errors
- [x] 5.2 `sam validate --lint` passes with zero errors
- [x] 5.3 `php artisan --version` in both services reports Laravel Framework 13.x
- [x] 5.4 `grep -r "deudor\|entidad\|situacion\|prestamo\|monto" --include="*.php"` returns zero hits
- [x] 5.5 `git status` confirms intended files only (no vendor/, no .env, no node_modules/)
