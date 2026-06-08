# Tasks: Phase 1 — Business Logic Core

## Review Workload Forecast

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1550 (additions + deletions) |
| 800-line budget risk | High |
| Suggested split | Work Unit 1 (infra/db/docs) → Work Unit 2 (domain+tests) → Work Unit 3 (app+infra+tests) |
| Delivery strategy | single-pr-default |

### Suggested Work Units

| Unit | Goal | Scope | Est. Lines |
|------|------|-------|------------|
| 1 | Foundation: restructure, conventions, Dockerfiles, migrations, models | Tasks 0–5 | ~480 |
| 2 | Domain: Value Objects, Entities, Events + tests | Tasks 6–8, 16a | ~360 |
| 3 | App+Infra: DTO, Parser, Transformer, SQS, LocalStack + tests | Tasks 9–15, 16b–c | ~710 |

## Phase 0: Foundation & Infrastructure

- [x] **0.1** `git mv importer-service services/importer` and `query-service services/query` — preserve history
- [x] **0.2** Update `docker-compose.yml`: rename services (`importer-service`→`importer`, `query-service`→`query`), build contexts (`./services/*`), env_file paths, volume mounts
- [x] **0.3** Update `AGENTS.md` CLI references (`docker-compose exec importer`, `query`)
- [x] **0.4** Update `docs/architecture/services.md` and `infrastructure.md` directory trees
- [x] **0.5** Create `docs/conventions/coding-standards.md`: PSR-12, PHP 8.5 features, Clean Architecture layer rules, strict typing, import ordering, English-only naming
- [x] **0.6** Create `docs/conventions/testing.md`: PHPUnit structure (Unit/Feature/Fixtures), AAA pattern, data providers, coverage targets (80% Domain, 100% App critical)
- [x] **1.1** Create `services/importer/Dockerfile`: `php:8.5-cli-alpine`, postgresql/pcntl extensions, Composer
- [x] **1.2** Create `services/query/Dockerfile` (same base, query-specific config)
- [ ] **1.3** Add `aws/aws-sdk-php` to `services/importer/composer.json`
- [x] **1.4** Update `.env` files: PostgreSQL connection, SQS/S3 LocalStack endpoints for both services
- [x] **2.1** Create `import_logs` migration (importer DB): id, filename, status, total_lines, total_debtors, total_entities, duration_ms, error_message, started_at, finished_at, timestamps
- [x] **2.2** Create `debtors` migration (query DB): id, identification_number (UNIQUE), max_situation, total_loan_amount, timestamps; indexes on max_situation, total_loan_amount DESC
- [x] **2.3** Create `entities` migration (query DB): id, entity_code (UNIQUE), total_loan_amount, timestamps; index on total_loan_amount DESC
- [x] **2.4** Create `app/Models/ImportLog` Eloquent model: table `import_logs`, casts (datetime, integer)
- [x] **2.5** Create `app/Models/Debtor` Eloquent model: table `debtors`, cast total_loan_amount to decimal:2
- [x] **2.6** Create `app/Models/Entity` Eloquent model: table `entities`, cast total_loan_amount to decimal:2

## Phase 1: Domain Layer (No Framework Dependencies)

- [ ] **3.1** Write test: Situation enum — valid codes, invalid codes throw ValueError, severity ordering (05>04>03>23>21>11>01), `worst()` static
- [ ] **3.2** `declare(strict_types=1)` `enum Situation: string` with 7 cases, `severity(): int`, `worst(self $a, self $b): self`
- [ ] **3.3** Write test: Amount — `fromBcraString("000000011,1")`→11.1, comma→period, add(), toFloat(), zero/edge cases
- [ ] **3.4** `final readonly class Amount`: constructor promotion, `fromBcraString(string $raw): self`, `add(self $other): self`, `toFloat(): float`
- [ ] **3.5** Write test: Cuit — valid 11-digit accepted, whitespace trimmed, empty rejected with InvalidArgumentException
- [ ] **3.6** `final readonly class Cuit`: constructor validates 11 digits + trim, `value(): string`
- [ ] **3.7** `enum IdentificationType: string` with single case `Cuit = '11'`
- [ ] **3.8** Write test: DebtorRecord — construction with Cuit+Situation+Amount via named arguments, readonly properties
- [ ] **3.9** `final readonly class DebtorRecord`: identificationNumber (Cuit), maxSituation (Situation), totalLoans (Amount)
- [ ] **3.10** Write test: EntityRecord — construction with entityCode + Amount
- [ ] **3.11** `final readonly class EntityRecord`: entityCode (string), totalLoans (Amount)
- [ ] **3.12** Write test: DebtorProcessed — immutable DTO, payload fields match input
- [ ] **3.13** `readonly class DebtorProcessed`: identificationNumber (string), maxSituation (string), totalLoans (float), processedAt (DateTimeImmutable)
- [ ] **3.14** Write test: EntityProcessed — immutable, payload fields match
- [ ] **3.15** `readonly class EntityProcessed`: entityCode (string), totalLoans (float), processedAt (DateTimeImmutable)
- [ ] **3.16** Write test: ImportCompleted — immutable, payload fields match
- [ ] **3.17** `readonly class ImportCompleted`: filename (string), totalDebtors (int), totalEntities (int), durationMs (int), completedAt (DateTimeImmutable)

## Phase 2: Application Layer

- [ ] **4.1** `readonly class BcraRecordDTO`: 24 typed properties matching BCRA field positions (docs/architecture/file-format.md)
- [ ] **4.2** Write test: BcraFileParser — 10-line fixture parses all fields, RN-04 filters tipo≠'11', RN-05 filters invalid situation, ISO-8859-1→UTF-8 conversion, returns LazyCollection
- [ ] **4.3** `final class BcraFileParser`: SplFileObject→LazyCollection, mb_convert_encoding, subst fixed-position (24 fields), filters tipo!='11' + invalid situation codes
- [ ] **4.4** Write test: BcraDataTransformer — known records→MAX situation grouping, SUM loans per debtor, SUM loans per entity, situation severity ordering
- [ ] **4.5** `final class BcraDataTransformer`: groupBy identificationNumber→MAX(situation)+SUM(loans), groupBy entityCode→SUM(loans), batch size 500
- [ ] **4.6** `interface EventPublisher` in `App\Application\Ports\`: publishDebtorProcessed, publishEntityProcessed, publishImportCompleted — no Laravel imports

## Phase 3: Infrastructure Layer

- [ ] **5.1** `class SqsEventPublisher implements EventPublisher`: SqsClient via DI, JSON serialization with MessageAttributes (event_type), per-queue routing from config
- [ ] **5.2** `class LocalstackSetupCommand` (Artisan command): idempotent S3 bucket + 3 SQS queue creation via LocalStack endpoint, `localstack:setup` signature
- [ ] **5.3** Write integration test: SqsEventPublisher — publishes to correct queue (against LocalStack)

## Phase 4: Test Fixtures & Verification

- [ ] **6.1** Create `tests/Fixtures/bcra_10_lines.txt`: 10 valid records (all tipo=11, valid situations)
- [ ] **6.2** Create `tests/Fixtures/bcra_invalid_situation.txt`: mix of valid+invalid situation codes
- [ ] **6.3** Create `tests/Fixtures/bcra_mixed_types.txt`: mix of tipo=11 and tipo!=11 records
- [ ] **6.4** Run full test suite: `php artisan test` — all Unit and Feature tests pass
- [ ] **6.5** Verify `docker-compose up -d` builds and starts with new service names

## Summary

| Batch | Tasks | Est. Lines | Parallel? |
|-------|-------|-----------|-----------|
| Foundation (0–2.6) | 0.1–2.6 | ~480 | Some (0.1+0.5+1.1) |
| Domain (3.1–3.17) | 3.1–3.17 | ~360 | Independent of infra |
| Application (4.1–4.6) | 4.1–4.6 | ~280 | Depends on domain |
| Infrastructure (5.1–5.3) | 5.1–5.3 | ~220 | Depends on app layer |
| Fixtures & Verify (6.1–6.5) | 6.1–6.5 | ~210 | Depends on all above |

**Total estimated: ~1550 lines | High budget risk | Recommend 3 work units**

### Risks
- **Budget**: ~1550 lines exceeds 800-line limit — consider splitting into 3 commits/slices
- **Parser positions**: Must match corrected leame-deudores.md positions exactly (not older SDD positions)
- **TDD discipline**: Writing tests before implementation for all Value Objects requires precise failure assertions
- **LocalStack**: CI environment must have LocalStack running for SQS integration tests
- **PHP 8.5 features**: Ensure Docker image php:8.5-cli-alpine supports enums/readonly/match

### Next Step
Confirm whether to split into 3 work units (chained commits) or proceed as single change with size exception before `sdd-apply`.
