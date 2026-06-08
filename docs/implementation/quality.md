# Code Quality Standards

> **High weight** criterion in evaluation.

## PHP Standards

- **`declare(strict_types=1)`** in EVERY PHP file — no exceptions
- Use **PHP 8.5 features** correctly:
  - **Typed properties** with strict types (no mixed unless justified)
  - **Readonly classes** for DTOs and Value Objects
  - **Enums** for finite state sets (e.g., `Situacion`, `ImportStatus`)
  - **Constructor property promotion** (no manual property assignment)
  - **Named arguments** for clarity in multi-parameter calls
  - **Match expressions** instead of switch statements
  - **Intersection types** and **union types** where appropriate
  - **Never return type** for functions that always throw or exit
  - **Fibers** for concurrent I/O if applicable
  - **First-class callable syntax** (`$fn = strlen(...)`)
  - **Array unpacking with string keys**
  - **Pure intersection types** and `true`/`false`/`null` standalone types
- Return types on ALL methods and functions — no implicit returns

## Architecture Standards

- Apply **Single Responsibility Principle**: each class has a single reason to change
- Apply **Dependency Inversion Principle**: dependencies towards interfaces, not implementations
- Controllers must not contain business logic (delegate to Services/Use Cases)
- Use **Laravel Form Requests** for API input validation
- Use **API Resources** for response serialization (never return Eloquent models directly)
- Error handling with **global Handler**, not try/catch in controllers
- Logging with structured context (`Log::info('...', ['key' => 'value'])`)
- Avoid N+1 queries (use `query builder` directly for bulk upsert)
- Use **Domain Events** for communication between bounded contexts
- Implement **idempotency** in event handlers (upsert instead of insert)
