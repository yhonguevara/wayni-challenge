# Archive Report: Phase 1 — Business Logic Core

**Change:** phase-1-business-logic
**Archived:** 2026-06-08
**Artifact store:** both (OpenSpec + Engram)

---

## Executive Summary

Phase 1 implemented the BCRA file processing pipeline for the Wayni Deudores Processor: fixed-position parser, domain value objects/entities, data transformer, domain events, and SQS event publishing. The monorepo was restructured to group services under `services/`, and coding/testing conventions were established to govern all future development.

The implementation followed Clean Architecture inside-out: Domain → Application → Infrastructure, with TDD for all domain value objects. The processing pipeline handles 171-character fixed-width BCRA lines, filters by CUIT-only records (RN-03) and valid situations (RN-04), aggregates debtors by MAX(situation) + SUM(loans) (RN-01) and entities by SUM(loans) (RN-02), and publishes domain events to SQS.

## Implementation Statistics

| Metric | Value |
|--------|-------|
| Total tasks | 37 |
| Work units | 3 (Foundation, Domain, App+Infra) |
| Tests | 81 (76 unit + 4 skipped integration + 1 added during fix) |
| Assertions | 208 |
| Verification result | CONDITIONAL PASS → PASS after fixes |
| Lines of code (est.) | ~1,550 |
| PHP Files created | ~25 |

## Work Unit Breakdown

### WU1: Foundation (Tasks 0.1–2.6) — ~480 lines

- **Monorepo restructure**: `git mv importer-service/ → services/importer/`, `query-service/ → services/query/`. Updated `docker-compose.yml` (service names, build contexts, env_file paths, volumes). Updated `AGENTS.md` and `docs/architecture/services.md` + `infrastructure.md`.
- **Conventions**: Created `docs/conventions/coding-standards.md` (PSR-12, PHP 8.5 enums/readonly/match, Clean Architecture layer rules, strict typing, English naming) and `docs/conventions/testing.md` (PHPUnit structure, AAA pattern, data providers, fixtures, 80%+ Domain / 100% App coverage targets).
- **Dockerfiles**: Multi-stage `php:8.5-cli-alpine` for both services with postgresql/pcntl extensions, Composer.
- **Database Migrations**: `import_logs` (importer DB — 12 columns), `debtors` (query DB — UNIQUE on identification_number, indexes), `entities` (query DB — UNIQUE on entity_code, index).
- **Eloquent Models**: `ImportLog`, `Debtor`, `Entity` with proper casts and table mappings.
- **Dependencies**: `aws/aws-sdk-php` added to importer.

### WU2: Domain Layer (Tasks 3.1–3.17) — ~360 lines

- **Value Objects**: `Situation` (backed string enum with 7 cases and severity ordering), `Amount` (BCRA format parsing `"000000011,1" → 11.1`, addition, immutable), `Cuit` (11-digit validation, whitespace trimming), `IdentificationType` (backed enum for CUIT='11').
- **Entities**: `DebtorRecord` (Cuit + Situation + Amount), `EntityRecord` (entityCode + Amount) — both `final readonly class`.
- **Domain Events**: `DebtorProcessed`, `EntityProcessed`, `ImportCompleted` — all `readonly class` implementing `DomainEvent` interface with `toArray()` and `occurredAt()`.
- **Layer purity**: Zero Laravel/Illuminate imports in Domain.

### WU3: Application + Infrastructure (Tasks 4.1–5.3) — ~710 lines

- **DTO**: `BcraRecordDTO` — 24 typed properties matching BCRA field positions.
- **Parser**: `BcraFileParser` — `SplFileObject → LazyCollection<BcraRecordDTO>`, `mb_convert_encoding` (ISO-8859-1→UTF-8), fixed-position `substr` extraction (24 fields), filters for tipo_identificacion='11' + valid situation codes.
- **Transformer**: `BcraDataTransformer` — groups by identificationNumber (MAX situation + SUM loans per debtor) and entityCode (SUM loans per entity), batch size 500.
- **EventPublisher**: Interface in `App\Application\Ports\` with `publish(DomainEvent)` / `publishBatch(array)`. Implementation: `SqsEventPublisher` with JSON serialization + MessageAttributes.
- **Console**: `localstack:setup` Artisan command — idempotently creates S3 bucket + 3 SQS queues (`debtor-events`, `entity-events`, `import-completed`).
- **Test Fixtures**: `bcra_10_lines.txt`, `bcra_invalid_situation.txt`, `bcra_mixed_types.txt`, `bcra_edge_cases.txt`.

## Verification Details

### Final Verification Status: PASS (CONDITIONAL PASS → PASS after fixes)

The verification report identified 1 CRITICAL and 7 WARNING issues. All were addressed before archiving:

| Issue | Severity | Resolution |
|-------|----------|------------|
| CRITICAL-001: ImportCompleted event payload mismatch | CRITICAL | Aligned payload to spec: `filename`, `totalDebtors`, `totalEntities`, `durationMs`, `completedAt` |
| WARNING-001: Missing `worst()` static method on Situation | WARNING | Accepted — `isWorseThan()` is functionally equivalent |
| WARNING-002/003: Property naming deviations (DebtorRecord, EntityRecord) | WARNING | Accepted — `situation`/`loansAmount` names used consistently in transformer |
| WARNING-004: `processedAt` not a constructor param | WARNING | Accepted — `occurredAt()` generates timestamp, events are immutable |
| WARNING-005: Generic EventPublisher vs typed methods | WARNING | Accepted — generic interface is more flexible for batch publishing |
| WARNING-006: Missing DESC indexes | WARNING | Accepted — indexes work for filtering; Phase 2 can optimize |
| WARNING-007: Docker PailServiceProvider | WARNING | Fixed — Pail removed from cached providers in Dockerfile |

### Artifacts Verified

| Artifact | Verification |
|----------|-------------|
| 24 field positions | ✅ All verified against leame-deudores.md §1 |
| RN-01 (MAX situation grouping) | ✅ Tested with severity ordering |
| RN-02 (SUM loans per entity) | ✅ Tested with multi-entity fixtures |
| RN-03 (idempotent upsert) | ✅ UNIQUE constraint on identification_number |
| RN-04 (tipo_identificacion='11') | ✅ Tested with mixed types fixture |
| RN-05 (situation severity ordering) | ✅ Tested with 7 valid codes |
| RN-06 (comma→period amounts) | ✅ Tested with `fromBcraString("000000011,1")` |
| RN-07 (ISO-8859-1→UTF-8) | ✅ mb_convert_encoding applied before extraction |
| RN-08 (streaming via LazyCollection) | ✅ SplFileObject → LazyCollection pattern |
| Docker compose config | ✅ Valid (`docker compose config --quiet` exit 0) |
| Strict typing | ✅ Zero PHP files missing `declare(strict_types=1)` |
| No Spanish identifiers | ✅ Zero matches in identifiers |
| Domain layer purity | ✅ Zero Laravel imports in Domain/ |

## Lessons Learned

### Technical

1. **Fixed-position parsing is error-prone**: Field positions must be verified against the source document. Applied corrections from leame-deudores.md §180-192 to ensure accuracy.
2. **LazyCollection is idiomatic but requires care**: `SplFileObject` + `LazyCollection` provides memory-safe streaming for 6GB files, but the transformer must consume the collection eagerly for aggregation.
3. **DESC indexes in PostgreSQL**: Laravel's Blueprint `index()` creates ascending indexes by default. DESC indexes require raw SQL (`DB::statement()`). This was deferred to Phase 2 since the read model isn't consumed yet.
4. **Docker Pail conflict**: `laravel/pail` in `require-dev` gets cached during `--no-dev` builds. Fixed by disabling plugin discovery in the Dockerfile.

### Process

1. **3 work units worked well**: Foundation → Domain → App+Infra gave clean review boundaries. Chained PRs would have been ideal but single PR was manageable given all 3 WUs were on the same phase.
2. **TDD for Domain was successful**: Writing tests before implementation for Value Objects ensured correct behavior from the start and caught edge cases early.
3. **Verify gate caught critical contract mismatch**: The ImportCompleted event payload mismatch (CRITICAL-001) would have broken Phase 2. The verification step proved its value.

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| PK type | BIGSERIAL | Simpler for read-heavy query DB, matches data-model.md |
| Table naming | English | `debtors`, `entities` — per naming convention |
| Streaming | SplFileObject + LazyCollection | Idiomatic Laravel, memory-safe |
| Batch size | 500 records | Balances memory (~2MB) and SQS throughput |
| Event format | JSON body + MessageAttributes | Enables SQS filtering |
| Amount storage | NUMERIC(18,2) | Prevents overflow on aggregation |
| Situation ordering | Backed enum + severity() | Self-documenting, type-safe |
| Docker base | php:8.5-cli-alpine | Simpler for `artisan serve`, ECS handles load balancing |

## Artifacts Created

### Specs Synced to Baseline

| Domain | Action |
|--------|--------|
| `monorepo-restructure` | New — 4 requirements (REQ-MONO-001 through 004) |
| `coding-conventions` | New — 4 requirements (REQ-CONV-001 through 004) |
| `business-logic-core` | New — 10 requirements (REQ-BIZ-001 through 010) |
| `domain-events` | New — 6 requirements (REQ-EVT-001 through 006) |
| `database-schema` | New — 6 requirements (REQ-DB-001 through 006) |
| `development-environment` | Updated — REQ-DEV-001 (service names), REQ-DEV-004 (port refs) |
| `project-scaffolding` | Updated — REQ-SCAF-001 (dir structure), REQ-SCAF-005 (conventions docs) |

### Key Files

| File | Description |
|------|-------------|
| `services/importer/Dockerfile` | Multi-stage PHP 8.5 CLI Alpine build |
| `services/query/Dockerfile` | Same base, query-specific config |
| `services/importer/database/migrations/*_create_import_logs_table.php` | Import tracking table |
| `services/query/database/migrations/*_create_debtors_table.php` | Debtor read model with indexes |
| `services/query/database/migrations/*_create_entities_table.php` | Entity read model with indexes |
| `services/importer/app/Domain/ValueObjects/Situation.php` | Backed enum, severity ordering, 7 cases |
| `services/importer/app/Domain/ValueObjects/Amount.php` | Readonly, BCRA format parsing |
| `services/importer/app/Domain/ValueObjects/Cuit.php` | Readonly, 11-digit validation |
| `services/importer/app/Domain/ValueObjects/IdentificationType.php` | Backed enum (only '11') |
| `services/importer/app/Domain/Entities/DebtorRecord.php` | Domain entity with Value Object properties |
| `services/importer/app/Domain/Entities/EntityRecord.php` | Domain entity |
| `services/importer/app/Domain/Events/DebtorProcessed.php` | Domain event DTO |
| `services/importer/app/Domain/Events/EntityProcessed.php` | Domain event DTO |
| `services/importer/app/Domain/Events/ImportCompleted.php` | Domain event DTO |
| `services/importer/app/Application/DTOs/BcraRecordDTO.php` | 24-field parsed line data |
| `services/importer/app/Application/Parser/BcraFileParser.php` | Streaming parser with filters |
| `services/importer/app/Application/Transformer/BcraDataTransformer.php` | Aggregation logic |
| `services/importer/app/Application/Ports/EventPublisher.php` | Interface for event publishing |
| `services/importer/app/Infrastructure/Messaging/SqsEventPublisher.php` | SQS implementation |
| `services/importer/app/Infrastructure/Console/LocalstackSetupCommand.php` | `localstack:setup` artisan command |
| `docs/conventions/coding-standards.md` | PSR-12, PHP 8.5, Clean Architecture rules |
| `docs/conventions/testing.md` | PHPUnit, AAA, fixtures, coverage targets |

## Conventions Established

- **English-only naming**: All identifiers in English (Debtor, Entity, Situation, Loans, Amount)
- **Environment naming**: "stg" not "staging"
- **Clean Architecture**: Domain / Application / Infrastructure layers with strict dependency rules
- **Strict typing**: `declare(strict_types=1)` in every PHP file
- **PHP 8.5 features**: enums, readonly classes, constructor promotion, match expressions
- **Domain purity**: No framework dependencies in Domain layer
- **TDD for domain**: Tests before implementation for all Value Objects

## Next Phase Recommendations

### Phase 2: Orchestration and Query API

Priority order:

1. **Import Orchestrator** (Application layer): Parse → Transform → Publish Events → Notify. Coordinates the full pipeline using the Phase 1 components.
2. **ProcessBcraFile Job** (Infrastructure): Queueable job that runs the orchestrator asynchronously.
3. **Upload Controller** (API layer): `POST /upload` endpoint that stores the file and dispatches the job.
4. **Notification System**: Log, Webhook, and SQS notification channels for import completion.
5. **Query API Event Handlers**: `UpsertDeudor` and `UpsertEntity` handlers that consume SQS events and update the read model.
6. **Query API Endpoints**:
   - `GET /debtors/{cuit}` — Single debtor lookup
   - `GET /entities/{code}` — Single entity lookup
   - `GET /debtors/top/{n}` — Top N debtors by loan amount
   - `GET /debtors?situation=X` — Filter debtors by situation
7. **API Resources and Form Requests**: Laravel API Resources for response formatting, Form Requests for validation.

### Technical Debt to Address

- Add `DESC` indexes on `total_loan_amount` for top-N queries (raw SQL migration)
- Add PHPStan or Psalm static analysis (recommended in config.yaml)
- Add Laravel Pint for automated formatting

---

## Archive Metadata

**Archive path:** `openspec/changes/archive/2026-06-08-phase-1-business-logic/`
**Engram topic_key:** `sdd/phase-1-business-logic/archive-report`
**Intentional archive warnings:** None — all CRITICAL issues resolved, WARNING-level deviations documented and accepted
