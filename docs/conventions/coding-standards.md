# Coding Standards — PHP 8.5 · Laravel 13 · Clean Architecture

## PSR-12 Compliance

All PHP code MUST follow PSR-12. Run `php-cs-fixer check` or `pint` before commit.

## PHP 8.5 Features

Prefer modern PHP constructs over legacy patterns:

| Legacy | Modern |
|--------|--------|
| `class` constants / arrays | `enum Situation: string` for fixed value sets |
| Setters for immutable data | `readonly` properties, constructor promotion |
| Positional args with unclear meaning | Named arguments for clarity |
| `switch` statements | `match` expressions |

```php
// Enums
enum Situation: string {
    case Normal = '01';
    case ConProblemas = '03';
    // ...
}

// Constructor promotion + readonly
final readonly class Cuit {
    public function __construct(
        private string $value,
    ) {}
}

// Named arguments
$record = new DebtorRecord(
    identificationNumber: $dto->identificationNumber,
    maxSituation: Situation::Normal,
    totalLoans: new Amount(1500.5),
);

// Match over switch
$label = match($situation) {
    Situation::Normal => 'Normal',
    Situation::ConProblemas => 'Con problemas',
    default => 'Unknown',
};
```

## Laravel Conventions

- **Helpers**: Use `Str::uuid()`, `Carbon::now()`, `Arr::get()`, `collect()` — never raw equivalents
- **Dependency injection**: Inject interfaces via constructor in business logic. Facades allowed only in service providers and commands
- **Validation**: Form Requests for controller validation — never inline in controller methods
- **Responses**: API Resources for response transformation — never return Eloquent models directly
- **Commands**: `php artisan` commands for all CLI operations (migrations, processing, setup)

## Clean Architecture Patterns

### Layer dependency rules

```
Domain ← no dependencies on framework or infrastructure
Application ← depends only on Domain
Infrastructure ← depends on Domain + Application, implements Application ports
```

| Layer | Allowed imports | Forbidden imports |
|-------|----------------|-------------------|
| Domain | PHP built-in | Eloquent, Laravel facades, PDO |
| Application | Domain, PHP built-in | Eloquent, Laravel facades |
| Infrastructure | Domain, Application, Laravel, AWS SDK | — |

### Domain layer conventions

- **Value Objects**: `final readonly class`, `__invoke()` or `fromString()` factory, `equals()` comparison, `__toString()` for serialization
- **Entities**: MAY use `readonly` properties, consume Value Objects for business concepts, contain NO persistence logic
- **Domain Events**: Simple immutable DTOs. Constructor only. No behavior methods. Named in past tense (`DebtorProcessed`)

### Application layer conventions

- Orchestrate domain logic only — no business rules
- DTOs for input/output boundaries (`BcraRecordDTO`)
- Ports (interfaces) define contracts that Infrastructure fulfills

### Infrastructure layer conventions

- Implement Application ports only — no direct domain logic
- Eloquent models exist ONLY in Infrastructure
- Repositories wrap Eloquent, return Domain Entities

## Imports and Namespaces

```php
declare(strict_types=1);

// 1. PHP built-in
use SplFileObject;

// 2. Laravel / Framework
use Illuminate\Support\LazyCollection;

// 3. Project
use App\Domain\Entities\DebtorRecord;
use App\Domain\ValueObjects\Situation;
```

- One import per line
- No aliases unless name collision is unavoidable
- No wildcard imports (`use App\Domain\*`)

## Type Hints

- `declare(strict_types=1)` at top of EVERY PHP file
- Type hint EVERYTHING: parameters, return types, properties
- Use union types: `string|int`
- Use nullable types: `?string` (prefer `string|null` in union types for clarity)

```php
declare(strict_types=1);

final readonly class Amount {
    public function __construct(
        private float $value,
    ) {}

    public function add(self $other): self { ... }
    public function toFloat(): float { ... }
}
```

## Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `BcraFileParser` |
| Methods/properties | camelCase | `parseLine`, `identificationNumber` |
| Constants | UPPER_SNAKE_CASE | `MAX_FILE_SIZE` |
| Database columns | snake_case | `identification_number` |
| Enum cases | PascalCase | `Situation::Normal` |
| Language | **English-only** | See `naming.md` |

---

*Last updated: Phase 1 SDD*
