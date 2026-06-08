# Testing Standards — Phase 1

## Testing Framework

PHPUnit (bundled with Laravel 13). Run via `php artisan test` within each service container.

## Test Structure

```
services/importer/tests/
├── Unit/           # Domain + Application layer tests (no DB, no containers)
│   ├── Domain/
│   │   ├── Entities/
│   │   └── ValueObjects/
│   └── Application/
│       ├── Parsers/
│       └── Transformers/
├── Feature/        # Infrastructure + Integration tests
│   └── Infrastructure/
│       └── Publishers/
└── Fixtures/       # Test data files
    ├── bcra_10_lines.txt
    ├── bcra_1000_lines.txt
    ├── bcra_invalid_situation.txt
    └── bcra_rn04_filtered.txt
```

## Test Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Class | `{ClassName}Test` | `BcraFileParserTest` |
| Method | `test_{method}_{scenario}` | `test_parse_valid_line` |
| Description | Explain the scenario | `test_parse_rn04_filters_non_11_identification` |

## Test Patterns

### Arrange-Act-Assert (AAA)

```php
public function test_parse_valid_line(): void
{
    // Arrange
    $parser = new BcraFileParser();
    $line = '...raw BCRA line...';

    // Act
    $dto = $parser->parseLine($line);

    // Assert
    $this->assertEquals('20345123458', $dto->identificationNumber);
}
```

### Data providers

```php
/** @dataProvider invalidSituationsProvider */
public function test_rejects_invalid_situation(string $situation): void
{
    // ...
}

public static function invalidSituationsProvider(): array
{
    return [['XX'], ['00'], ['99']];
}
```

### Factories

Use factory methods for domain entities in tests:

```php
private function createDebtorRecord(
    string $cuit = '20345123458',
    float $loans = 1500.5,
): DebtorRecord {
    return new DebtorRecord(
        identificationNumber: new Cuit($cuit),
        maxSituation: $this->createSituation('01'),
        totalLoans: new Amount($loans),
    );
}
```

## Domain Testing

- **Value Objects**: Test construction with valid values, rejection with invalid values, equality comparison, edge cases (min, max, zero, empty)
- **Entities**: Test business rules, immutability of Value Object properties
- **Domain Events**: Test payload fields match input, serialization round-trips

## Application Testing

- **Parsers**: Test with fixture files at 3 sizes: 10 lines (smoke), 1000 lines (typical), 10000 lines (performance)
- **Transformers**: Test with known input arrays → expected output (MAX situation, SUM loans)
- **Mocks**: Mock infrastructure dependencies (repositories, event publishers) — test Application behavior, not side effects

## Infrastructure Testing

- **SQS/S3**: Integration tests against LocalStack (`localstack:4566`)
- **HTTP**: Feature tests for API endpoints using `RefreshDatabase` trait
- **Database**: Use `RefreshDatabase` for isolated test DB state

```php
class EventPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_to_correct_queue(): void
    {
        // Arrange + Act against localstack:4566
    }
}
```

## Fixtures

- Location: `services/importer/tests/Fixtures/`
- Naming: `bcra_{descriptor}.txt` (e.g., `bcra_10_lines.txt`, `bcra_invalid_situation.txt`)
- Include both valid and invalid examples
- First line of fixture file MUST contain a comment with format description

```
# BCRA fixture: 10 valid debtor records, all tipo_identificacion=11, all situations valid
...data lines...
```

## Coverage Targets

| Layer | Target | Rationale |
|-------|--------|-----------|
| Domain (ValueObjects, Entities, Events) | 80%+ | Core business rules must be tested |
| Application (Parsers, Transformers) | 100% | Critical paths — file ingestion correctness |
| Infrastructure (Publishers, Repositories) | — | Integration tests over unit coverage |

---

*Last updated: Phase 1 SDD*
