# Coding Conventions Specification

## Purpose

Define the coding standards and testing conventions documents that govern all PHP code in the project.

## Requirements

### REQ-CONV-001: Coding Standards Document

The project MUST contain `docs/conventions/coding-standards.md` covering PSR-12, PHP 8.5 features, Clean Architecture layer rules, type hints, and import ordering.

#### Scenario: Document exists with required sections

- GIVEN `docs/conventions/coding-standards.md` is read
- WHEN sections are inspected
- THEN it MUST contain: PSR-12 compliance, PHP 8.5 features table, Laravel conventions, Clean Architecture layer rules, import grouping, type hints, and naming table

#### Scenario: Strict typing mandated

- GIVEN the coding standards document
- WHEN type hint rules are read
- THEN `declare(strict_types=1)` MUST be required at the top of every PHP file
- AND all parameters, return types, and properties MUST be type-hinted

#### Scenario: Clean Architecture layer rules documented

- GIVEN the layer dependency rules section
- WHEN inspected
- THEN Domain MUST forbid Eloquent, Laravel facades, and PDO imports
- AND Application MUST forbid Eloquent and Laravel facades
- AND Infrastructure MAY import all layers

### REQ-CONV-002: PHP 8.5 Feature Usage

Code MUST prefer modern PHP 8.5 constructs over legacy patterns.

| Construct | When to use |
|-----------|-------------|
| `enum` | Fixed value sets (e.g., `Situation`) |
| `readonly` + constructor promotion | Immutable Value Objects |
| `match` | Multi-branch expressions (over `switch`) |
| Named arguments | Clarity at call sites |

#### Scenario: Enum for situation codes

- GIVEN a `Situation` type is defined
- WHEN inspected
- THEN it MUST be a PHP `enum` backed by `string` with cases for each valid code

#### Scenario: Value Objects are readonly

- GIVEN any Value Object class (e.g., `Cuit`, `Amount`)
- WHEN inspected
- THEN it MUST be declared `final readonly class` with constructor promotion

### REQ-CONV-003: Testing Conventions Document

The project MUST contain `docs/conventions/testing.md` covering PHPUnit usage, test structure, AAA pattern, data providers, fixtures, and coverage targets.

#### Scenario: Document exists with required sections

- GIVEN `docs/conventions/testing.md` is read
- WHEN sections are inspected
- THEN it MUST contain: test directory structure, AAA pattern examples, data provider examples, fixture naming, and coverage targets table

#### Scenario: Coverage targets defined

- GIVEN the coverage targets section
- WHEN inspected
- THEN Domain layer MUST target 80%+
- AND Application critical paths MUST target 100%

### REQ-CONV-004: Test Directory Structure

Tests MUST follow the structure defined in `testing.md`.

```
tests/
├── Unit/           # Domain + Application (no DB)
├── Feature/        # Infrastructure + Integration
└── Fixtures/       # Test data files
```

#### Scenario: Unit tests isolated

- GIVEN `tests/Unit/` exists
- WHEN tests are run
- THEN they MUST NOT require database connections or external services

#### Scenario: Fixture files follow naming convention

- GIVEN `tests/Fixtures/` is listed
- WHEN inspected
- THEN files MUST match pattern `bcra_{descriptor}.txt`
