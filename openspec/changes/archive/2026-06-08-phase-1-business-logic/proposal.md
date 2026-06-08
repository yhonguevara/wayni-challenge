# Proposal: Phase 1 — Business Logic Core

## Intent

Implement the BCRA file parser, domain entities, and event publishing. First, restructure the monorepo to group services under `services/` for cleaner scaling and lower cognitive load.

## Scope

### In Scope
0. **Monorepo restructure**: Move `importer-service/` → `services/importer/`, `query-service/` → `services/query/`. Update `docker-compose.yml` (service names, build contexts, env_file paths, volumes), `AGENTS.md`, and `docs/architecture/` references.
0.5. **Coding and testing conventions**: Create `docs/conventions/coding-standards.md` (PSR-12, PHP 8.5 features, Clean Architecture patterns, type hints, imports) and `docs/conventions/testing.md` (PHPUnit, test structure, AAA pattern, data providers, fixtures, coverage targets).
1. Dockerfiles for both services (PHP 8.5 CLI Alpine)
2. Migrations: `import_logs` (importer), `debtors` + `entities` (query), with indexes
3. Eloquent models: ImportLog, Debtor, Entity
4. Domain: Value Objects (Situation, Amount, Cuit), Entities (DebtorRecord, EntityRecord), Events (DebtorProcessed, EntityProcessed, ImportCompleted)
5. Application: BcraRecordDTO, BcraFileParser (SplFileObject→LazyCollection, ISO-8859-1→UTF-8, filters RN-04/RN-05), BcraDataTransformer (MAX situation + SUM loans grouping RN-01/RN-02, comma→period amount parsing RN-07)
6. Infrastructure: EventPublisher interface, SqsEventPublisher, `localstack:setup` command
7. Unit tests: parser (10-line fixture) + transformer (known data)

### Out of Scope
- Notifications, import orchestrator/jobs, upload controller logic, query API endpoints/event handlers, ECS dispatch

## Capabilities

### New Capabilities
- `monorepo-restructure`: Services grouped under `services/` dir. Remove redundant `-service` suffix. Docker service names: `importer`, `query`.
- `coding-conventions`: PSR-12, PHP 8.5 features, Clean Architecture layer rules, type hints, imports. Docs: `coding-standards.md` + `testing.md`.
- `business-logic-core`: Fixed-position parser (171 chars, 24 fields), debtor/entity aggregation, streaming via LazyCollection
- `domain-events`: EventPublisher contract, SqsEventPublisher, LocalStack setup, domain event classes
- `database-schema`: import_logs, debtors (UNIQUE on identification_number, indexes on situation+loans), entities (index on loans)

### Modified Capabilities
- `project-scaffolding`: REQ-SCAF-005 extended — conventions dir now hosts 3 docs (naming, coding-standards, testing). Service dirs moved from root to `services/importer/` and `services/query/`; Clean Architecture dirs updated accordingly
- `development-environment`: `docker-compose.yml` service names renamed (`importer-service`→`importer`, `query-service`→`query`), build contexts updated

## Approach

Task 0 (restructure) → 0.5 (conventions) → inner→outer: Domain → Application → Infrastructure.

**0. Monorepo restructure**: `git mv importer-service services/importer`, `git mv query-service services/query`. Update `docker-compose.yml`: rename services (`importer-service`→`importer`, `query-service`→`query`, `query-worker`→`query-worker` — keep workers as-is), build contexts (`./importer-service`→`./services/importer`), env_file paths, volume mounts. Update `AGENTS.md` CLI references. Update `docs/architecture/services.md` and `infrastructure.md` directory trees. `infrastructure/template.yaml` has no hardcoded service paths — skip.

**0.5. Coding and testing conventions**: Create `docs/conventions/coding-standards.md` covering PSR-12, PHP 8.5 features (enums, readonly, constructor promotion, named args, match), Laravel conventions (DI over facades, Form Requests, API Resources), Clean Architecture layer dependency rules, import grouping, strict typing, and English-only naming. Create `docs/conventions/testing.md` covering PHPUnit, test structure (`Unit/`, `Feature/`, `Fixtures/`), AAA pattern, data providers, fixture files (10/1000/10000 lines), and coverage targets (80%+ Domain, 100% Application critical paths).

**1. Dockerfiles**: `php:8.5-cli-alpine`, postgresql/pcntl extensions, Composer, `artisan serve`

**2. Migrations**: `debtors` UNIQUE on `identification_number`, RN-03 idempotent upsert

**3. Parser**: Line-by-line `SplFileObject` → `LazyCollection<BcraRecordDTO>`, `mb_convert_encoding` ISO-8859-1→UTF-8, `substr()` fixed-position extraction, filter tipo_identificacion='11' + valid situations

**4. Transformer**: Group by identification_number → MAX(situation), SUM(loans). Group by entity_code → SUM(loans).

**5. Events**: DebtorProcessed + EntityProcessed per aggregate, ImportCompleted at end

**6. Infra**: SQS publish via `aws/aws-sdk-php`. `localstack:setup` idempotently creates S3 bucket + 3 queues.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `services/importer/` | Renamed | Moved from `importer-service/` |
| `services/query/` | Renamed | Moved from `query-service/` |
| `docs/conventions/coding-standards.md` | New | PSR-12, PHP 8.5, Clean Architecture, type hints, imports |
| `docs/conventions/testing.md` | New | PHPUnit, AAA, data providers, fixtures, coverage targets |
| `docker-compose.yml` | Modified | Service names, build contexts, env_file, volumes |
| `AGENTS.md` | Modified | CLI references (`docker-compose exec importer`, `query`) |
| `docs/architecture/services.md` | Modified | Directory tree + service name table |
| `docs/architecture/infrastructure.md` | Modified | Directory tree + monorepo structure section |
| `*/Dockerfile` | New | PHP 8.5 CLI Alpine with extensions |
| `*/database/migrations/` | New | 3 migration files |
| `services/importer/app/Domain/` | Modified | ValueObjects, Entities, Events |
| `services/importer/app/Application/` | Modified | DTO, Parser, Transformer |
| `services/importer/app/Infrastructure/` | Modified | EventPublisher + SQS impl |
| `both/app/Models/` | New | ImportLog, Debtor, Entity |
| `services/importer/tests/` | New | Parser + Transformer tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| BCRA format positions drift | Low | Corrections from leame-deudores.md §180-192 applied |
| Memory on 6GB files | Low | LazyCollection streaming, 512MB cap |
| SQS dedup on re-process | Med | Batch ID in events; idempotent upsert in Phase 2 |
| `git mv` loses history if done incorrectly | Low | Use `git mv`, verify `git log --follow` after move |

## Rollback Plan

Revert commit. Migrations include `down()` — `php artisan migrate:rollback`. Monorepo restructure reversed via `git revert`. No production data at risk.

## Dependencies

- Phase 0 completed (docker-compose, Clean Arch dirs, Laravel 13)
- `aws/aws-sdk-php` Composer package

## Success Criteria

- [ ] `services/importer/` and `services/query/` exist, `importer-service/` and `query-service/` removed
- [ ] `docs/conventions/coding-standards.md` exists with PSR-12, PHP 8.5, Clean Architecture, type hints, and import rules
- [ ] `docs/conventions/testing.md` exists with PHPUnit, AAA, data providers, fixtures, and coverage targets
- [ ] `docker-compose up -d` builds and starts all services with new names
- [ ] `docker-compose exec importer php artisan migrate` succeeds
- [ ] `docker-compose exec query php artisan migrate` succeeds
- [ ] Parser extracts all 24 fields from fixture (verified against leame-deudores.md positions)
- [ ] RN-04 (tipo='11') and RN-05 (valid situation) filters exclude invalid records
- [ ] Transformer produces correct MAX(situation) + SUM(loans) per debtor/entity
- [ ] `localstack:setup` creates S3 + 3 SQS queues idempotently
- [ ] SqsEventPublisher sends to correct queues
- [ ] All unit tests pass
