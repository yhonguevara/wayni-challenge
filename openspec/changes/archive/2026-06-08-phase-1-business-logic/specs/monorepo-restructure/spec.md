# Monorepo Restructure Specification

## Purpose

Define the directory restructure moving services from root-level to a `services/` parent directory, renaming service identifiers, and updating all references.

## Requirements

### REQ-MONO-001: Service Directory Relocation

The system MUST place both application services under a `services/` parent directory.

| From | To |
|------|-----|
| `importer-service/` | `services/importer/` |
| `query-service/` | `services/query/` |

#### Scenario: New directories exist

- GIVEN the restructure is applied
- WHEN the project root is listed
- THEN `services/importer/` and `services/query/` MUST exist
- AND `importer-service/` and `query-service/` MUST NOT exist

#### Scenario: Git history preserved

- GIVEN `git mv` was used for the move
- WHEN `git log --follow services/importer/composer.json` is run
- THEN commit history prior to the move MUST be visible

### REQ-MONO-002: Docker Compose Service Renaming

The `docker-compose.yml` MUST rename services and update build contexts.

| Old service name | New service name | Build context |
|------------------|------------------|---------------|
| `importer-service` | `importer` | `./services/importer` |
| `query-service` | `query` | `./services/query` |

#### Scenario: Compose builds with new names

- GIVEN `docker-compose.yml` is updated
- WHEN `docker-compose up -d` is executed
- THEN services `importer`, `query`, `importer-db`, `query-db`, `localstack` MUST start

#### Scenario: Build contexts resolve

- GIVEN the compose file references `./services/importer`
- WHEN Docker builds the `importer` service
- THEN it MUST find `Dockerfile` at `services/importer/Dockerfile`

### REQ-MONO-003: Documentation and Reference Updates

All documentation referencing old paths or service names MUST be updated.

#### Scenario: AGENTS.md updated

- GIVEN `AGENTS.md` is read
- WHEN CLI examples are inspected
- THEN references MUST use `docker-compose exec importer` (not `importer-service`)

#### Scenario: Architecture docs updated

- GIVEN `docs/architecture/services.md` and `docs/architecture/infrastructure.md` are read
- WHEN directory trees are inspected
- THEN they MUST reflect `services/importer/` and `services/query/`

### REQ-MONO-004: Environment File Path Updates

The `env_file` and `volumes` paths in `docker-compose.yml` MUST reference new service locations.

#### Scenario: Env file paths valid

- GIVEN `docker-compose.yml` defines `env_file` for the `importer` service
- WHEN inspected
- THEN the path MUST be `./services/importer/.env`

#### Scenario: Volume mounts valid

- GIVEN volume mounts are defined
- WHEN inspected
- THEN source paths MUST reference `./services/importer/` and `./services/query/`
