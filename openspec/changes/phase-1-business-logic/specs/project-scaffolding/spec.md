# Delta for Project Scaffolding

## MODIFIED Requirements

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
