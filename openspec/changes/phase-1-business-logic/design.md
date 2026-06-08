# Design: Phase 1 — Business Logic Core

## Technical Approach

Implement the BCRA file processing pipeline inside-out: Domain → Application → Infrastructure, preceded by monorepo restructure and Dockerfile creation. The importer service parses the 171-character fixed-width BCRA file line-by-line via `SplFileObject` + `LazyCollection`, aggregates debtors (MAX situation + SUM loans) and entities (SUM loans), then publishes domain events to SQS. All code follows Clean Architecture with strict layer boundaries — Domain has zero framework dependencies.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| PK type | UUID vs BIGSERIAL | UUIDs add index bloat; BIGSERIAL simpler but leaks ordering | **BIGSERIAL** — matches existing `data-model.md` baseline, simpler for read-heavy query DB |
| Table names | Spanish (`deudores`) vs English (`debtors`) | Consistency with naming convention | **English** (`debtors`, `entities`) — per `naming.md` enforcement rules |
| Streaming | `SplFileObject` vs `fopen`/`fgets` | SplFileObject is OOP, iterable; fgets is faster but manual | **SplFileObject** wrapped in `LazyCollection` — idiomatic Laravel, memory-safe |
| Batch size | 100 vs 500 vs 1000 | Smaller = less memory, more SQS calls; larger = opposite | **500 records/batch** — balances memory (~2MB/batch) with SQS throughput |
| Event format | JSON in SQS body vs separate attributes | Attributes enable filtering but add complexity | **JSON body** with `MessageAttributes` for event type routing |
| Amount storage | `NUMERIC(15,1)` vs `NUMERIC(18,2)` | BCRA format is 11+1; extra precision for future SUM overflow | **NUMERIC(18,2)** — matches `data-model.md`, prevents overflow on aggregation |
| Situation comparison | Alphabetical string vs enum ordinal | Alphabetical works because codes are designed that way | **PHP 8.5 backed enum** with explicit `severity()` method — safer, self-documenting |
| Dockerfile base | `php:8.5-cli-alpine` vs `php:8.5-fpm-alpine` + nginx | CLI simpler for `artisan serve`; FPM+nginx for production | **CLI Alpine** for Phase 1 — matches `infrastructure.md`, ECS handles load balancing |

## Data Flow

```
BCRA File (TXT, ISO-8859-1)
    │
    ▼
BcraFileParser (SplFileObject → LazyCollection<BcraRecordDTO>)
    │  Filters: tipo_identificacion='11', valid situation codes
    │  Converts: ISO-8859-1→UTF-8, comma→period amounts
    ▼
BcraDataTransformer (LazyCollection → [DebtorAggregate[], EntityAggregate[]])
    │  Groups by identification_number: MAX(situation), SUM(loans)
    │  Groups by entity_code: SUM(loans)
    │  Processes in batches of 500
    ▼
EventPublisher (SqsEventPublisher)
    │  DebtorProcessed → SQS debtor-events queue
    │  EntityProcessed → SQS entity-events queue
    │  ImportCompleted → SQS debtor-events queue (summary)
    ▼
Query Service (Phase 2 — consumes events, upserts read model)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `services/importer/` | Move | `git mv importer-service services/importer` |
| `services/query/` | Move | `git mv query-service services/query` |
| `docker-compose.yml` | Modify | Service names: `importer`, `query`; build contexts: `./services/*`; env_file paths updated |
| `services/importer/Dockerfile` | Create | Multi-stage PHP 8.5 CLI Alpine build |
| `services/query/Dockerfile` | Create | Same base, query-specific config |
| `services/importer/database/migrations/*_create_import_logs_table.php` | Create | Import tracking table |
| `services/query/database/migrations/*_create_debtors_table.php` | Create | Debtor read model with indexes |
| `services/query/database/migrations/*_create_entities_table.php` | Create | Entity read model with indexes |
| `services/importer/app/Domain/ValueObjects/Situation.php` | Create | Backed enum, severity ordering |
| `services/importer/app/Domain/ValueObjects/Amount.php` | Create | Readonly, BCRA format parsing |
| `services/importer/app/Domain/ValueObjects/Cuit.php` | Create | Readonly, 11-digit validation |
| `services/importer/app/Domain/ValueObjects/IdentificationType.php` | Create | Backed enum (only '11') |
| `services/importer/app/Domain/Entities/DebtorRecord.php` | Create | Domain entity with Value Object properties |
| `services/importer/app/Domain/Entities/EntityRecord.php` | Create | Domain entity |
| `services/importer/app/Domain/Events/DebtorProcessed.php` | Create | Domain event DTO |
| `services/importer/app/Domain/Events/EntityProcessed.php` | Create | Domain event DTO |
| `services/importer/app/Domain/Events/ImportCompleted.php` | Create | Domain event DTO |
| `services/importer/app/Application/DTOs/BcraRecordDTO.php` | Create | Parsed line data transfer object |
| `services/importer/app/Application/Parser/BcraFileParser.php` | Create | Streaming parser with filters |
| `services/importer/app/Application/Transformer/BcraDataTransformer.php` | Create | Aggregation logic |
| `services/importer/app/Application/Ports/EventPublisher.php` | Create | Interface for event publishing |
| `services/importer/app/Infrastructure/Messaging/SqsEventPublisher.php` | Create | SQS implementation |
| `services/importer/app/Infrastructure/Console/LocalstackSetupCommand.php` | Create | `localstack:setup` artisan command |
| `services/importer/tests/Unit/Domain/ValueObjects/SituationTest.php` | Create | Enum and severity tests |
| `services/importer/tests/Unit/Domain/ValueObjects/AmountTest.php` | Create | Parsing and edge case tests |
| `services/importer/tests/Unit/Domain/ValueObjects/CuitTest.php` | Create | Validation tests |
| `services/importer/tests/Unit/Application/Parser/BcraFileParserTest.php` | Create | Fixture-based parser tests |
| `services/importer/tests/Unit/Application/Transformer/BcraDataTransformerTest.php` | Create | Aggregation correctness tests |
| `services/importer/tests/Fixtures/bcra_10_lines.txt` | Create | Valid 10-line fixture |
| `services/importer/tests/Fixtures/bcra_invalid_situation.txt` | Create | Invalid situation codes |
| `services/importer/tests/Fixtures/bcra_mixed_types.txt` | Create | Mix of identification types |
| `AGENTS.md` | Modify | CLI references: `docker-compose exec importer`, `docker-compose exec query` |
| `docs/architecture/services.md` | Modify | Directory tree updated with `services/` prefix |
| `docs/architecture/infrastructure.md` | Modify | Monorepo structure section updated |
| `services/importer/composer.json` | Modify | Add `aws/aws-sdk-php` dependency |
| `services/importer/.env` | Modify | PostgreSQL connection, SQS/S3 LocalStack endpoints |
| `services/query/.env` | Modify | PostgreSQL connection |

## Interfaces / Contracts

### Domain Value Objects

```php
// Situation enum with severity ordering
enum Situation: string {
    case Normal = '01';
    case LowRisk = '21';
    case SpecialTreatment = '23';
    case MediumRisk = '03';
    case HighRisk = '04';
    case Unrecoverable = '05';
    case CoveredAssistance = '11';

    public function severity(): int; // 0-6, higher = worse
    public static function worst(self $a, self $b): self;
}

// Amount with BCRA format parsing
final readonly class Amount {
    public function __construct(private float $value) {}
    public static function fromBcraFormat(string $raw): self; // "11,1" → 11.1
    public function add(self $other): self;
    public function toFloat(): float;
}

// CUIT validation
final readonly class Cuit {
    public function __construct(private string $value) {} // validates 11 digits
    public function value(): string;
}
```

### Application Parser

```php
final class BcraFileParser {
    /** @return LazyCollection<int, BcraRecordDTO> */
    public function parse(string $filePath): LazyCollection;
}
```

### Event Publisher Port

```php
interface EventPublisher {
    public function publishDebtorProcessed(DebtorProcessed $event): void;
    public function publishEntityProcessed(EntityProcessed $event): void;
    public function publishImportCompleted(ImportCompleted $event): void;
}
```

### SQS Message Format

```json
{
    "event": "DebtorProcessed",
    "importId": "uuid",
    "payload": {
        "identificationNumber": "20345123458",
        "maxSituation": "05",
        "totalLoans": 1500.5
    },
    "occurredAt": "2026-06-08T12:00:00Z"
}
```

MessageAttributes: `event_type` = `DebtorProcessed` (for SQS filtering).

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit — Domain | Situation severity ordering, Amount BCRA parsing (comma→period, zero, max), Cuit validation (11 digits, non-numeric) | PHPUnit with data providers, no framework deps |
| Unit — Application | BcraFileParser: 10-line fixture, RN-04 filter (tipo≠'11'), RN-05 filter (invalid situation), ISO-8859-1 conversion, fixed-position extraction | Fixture files in `tests/Fixtures/`, assert DTO field values |
| Unit — Application | BcraDataTransformer: known input → MAX(situation) + SUM(loans) per debtor, SUM(loans) per entity | Hand-crafted input arrays, assert aggregation output |
| Integration | SqsEventPublisher sends to correct queue | LocalStack on port 4566, verify message receipt |

## Migration / Rollout

No migration required — this is a greenfield implementation. Monorepo restructure uses `git mv` to preserve history. Rollback: `git revert` the restructure commit.

## Open Questions

- [ ] Should `import_logs` track per-batch progress or only whole-file status?
- [ ] Confirm SQS queue names for LocalStack: `debtor-events`, `entity-events` (no environment suffix in dev)?
