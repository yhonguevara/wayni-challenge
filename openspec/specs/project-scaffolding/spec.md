# Project Scaffolding Specification

## Purpose

Define the Laravel 13 monorepo structure with Clean Architecture directory layout, English-only naming convention enforcement, and project-level configuration files.

## Requirements

### REQ-SCAF-001: Monorepo Directory Structure

The project MUST organize code into `services/importer/`, `services/query/`, and `infrastructure/` directories.

(Previously: Services were at root level as `importer-service/` and `query-service/`)

#### Scenario: Top-level directories exist

- GIVEN the project has been scaffolded
- WHEN the root directory is listed
- THEN `services/`, `infrastructure/`, `docs/`, and `openspec/` directories MUST exist

#### Scenario: Service directories under services/

- GIVEN the `services/` directory is listed
- WHEN inspected
- THEN `services/importer/` and `services/query/` MUST exist
- AND each MUST contain its own `composer.json`, `artisan`, and `.env.example`

#### Scenario: Service independence

- GIVEN both services exist
- WHEN inspected
- THEN no shared `vendor/` directory at root level
- AND each service has independent dependencies

### REQ-SCAF-002: Laravel 13 Application Scaffold

Each service MUST be a complete Laravel 13 application created via `composer create-project laravel/laravel:^13.0`.

#### Scenario: Artisan CLI functional

- GIVEN a service directory exists
- WHEN `php artisan --version` is executed inside the service
- THEN it reports Laravel Framework 13.x

#### Scenario: Default Laravel structure

- GIVEN a service directory exists
- WHEN inspected
- THEN standard Laravel directories (`app/`, `config/`, `database/`, `routes/`, `tests/`) MUST exist

### REQ-SCAF-003: Clean Architecture Directory Layout

Each service MUST organize application code under `app/` with `Domain/`, `Application/`, and `Infrastructure/` subdirectories.

#### Scenario: Architecture layers present

- GIVEN a service's `app/` directory is inspected
- WHEN listed recursively
- THEN `app/Domain/`, `app/Application/`, and `app/Infrastructure/` directories MUST exist

#### Scenario: Domain layer structure

- GIVEN `app/Domain/` exists
- WHEN inspected
- THEN it MUST contain `Entities/`, `ValueObjects/`, `Events/`, and `Repositories/` subdirectories

#### Scenario: Application layer structure

- GIVEN `app/Application/` exists
- WHEN inspected
- THEN it MUST contain `UseCases/`, `DTOs/`, and `Ports/` subdirectories

### REQ-SCAF-004: English-Only Naming Convention

ALL technical elements MUST use English naming. No Spanish identifiers are permitted in any artifact.

#### Scenario: Database naming

- GIVEN database tables and columns are defined
- WHEN inspected
- THEN table names MUST use English nouns: `debtors`, `entities`
- AND column names MUST use English: `identification_number`, `max_situation`, `total_loans`

#### Scenario: Code identifier naming

- GIVEN PHP source files exist
- WHEN `grep -r "deudor\|entidad\|situacion\|prestamo\|monto" --include="*.php"` is executed
- THEN zero matches MUST be found

#### Scenario: API endpoint naming

- GIVEN API routes are defined
- WHEN inspected
- THEN paths MUST use English: `/debtors/{cuit}`, `/entities/{code}`
- AND NOT Spanish: `/deudores/{cuit}`, `/entidades/{code}`

#### Scenario: Event naming

- GIVEN domain events are defined
- WHEN inspected
- THEN event class names MUST be English PascalCase: `DebtorProcessed`, `EntityProcessed`

### REQ-SCAF-005: Naming Convention Documentation

The project MUST document conventions in `docs/conventions/` with 3 documents: `naming.md`, `coding-standards.md`, and `testing.md`.

(Previously: Only `naming.md` was required)

#### Scenario: All convention documents exist

- GIVEN the project is scaffolded
- WHEN `docs/conventions/` is listed
- THEN `naming.md`, `coding-standards.md`, and `testing.md` MUST all exist

#### Scenario: naming.md content

- GIVEN `docs/conventions/naming.md` is read
- WHEN inspected
- THEN it MUST contain the key mappings table and enforcement rules

#### Scenario: coding-standards.md content

- GIVEN `docs/conventions/coding-standards.md` is read
- WHEN inspected
- THEN it MUST contain PSR-12, PHP 8.5 features, and Clean Architecture layer rules

#### Scenario: testing.md content

- GIVEN `docs/conventions/testing.md` is read
- WHEN inspected
- THEN it MUST contain PHPUnit structure, AAA pattern, and coverage targets

### REQ-SCAF-006: Git Ignore Configuration

The project MUST include a `.gitignore` at root level covering all generated and sensitive files.

#### Scenario: Gitignore coverage

- GIVEN `.gitignore` exists at root
- WHEN inspected
- THEN it MUST exclude `vendor/`, `.env`, `node_modules/`, `*.log`, `.idea/`, `.vscode/`, `docker-compose.override.yml`

### REQ-SCAF-007: Composer Dependencies

Each service MUST declare its PHP and Laravel dependencies in `composer.json`.

#### Scenario: PHP version constraint

- GIVEN a service's `composer.json` is inspected
- WHEN the `require` section is read
- THEN `php` MUST be constrained to `^8.5`
- AND `laravel/framework` MUST be `^13.0`
