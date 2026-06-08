# Delta for Development Environment

## MODIFIED Requirements

### REQ-DEV-001: Docker Compose Service Definitions

The system MUST define exactly 6 services in `docker-compose.yml`: `importer`, `query`, `query-worker`, `importer-db`, `query-db`, and `localstack`.

(Previously: 5 services — `query-worker` was missing from the compose file)

#### Scenario: All services start successfully

- GIVEN a valid `docker-compose.yml` exists at project root
- WHEN `docker-compose up -d` is executed
- THEN all 6 services reach a healthy or running state
- AND `docker-compose ps` shows 6 running containers

#### Scenario: Service isolation

- GIVEN both services are running
- WHEN `importer` connects to a database
- THEN it MUST connect only to `importer-db`
- AND `query` MUST connect only to `query-db`

### REQ-DEV-007: Query Worker Service Definition

The system MUST define a `query-worker` service in `docker-compose.yml` that uses the same image as `query` but runs the Laravel queue worker command.

#### Scenario: Query worker runs queue:work

- GIVEN `docker-compose.yml` is configured
- WHEN the `query-worker` service starts
- THEN it MUST execute `php artisan queue:work sqs --sleep=3 --tries=3 --max-time=3600`
- AND it MUST NOT expose any host port

#### Scenario: Query worker shares query dependencies

- GIVEN `query-worker` is defined
- WHEN its configuration is inspected
- THEN it MUST use the same `env_file` as `query` (`./services/query/.env`)
- AND it MUST `depends_on` `query-db` (healthy) and `localstack` (healthy)

### REQ-DEV-008: Init Script for Service Bootstrap

The system MUST provide an `init.sh` script at project root that runs database migrations for both services and sets up LocalStack resources.

#### Scenario: First-time initialization

- GIVEN all Docker services are running
- WHEN `init.sh` is executed
- THEN it MUST run `php artisan migrate --force` inside the `importer` container
- AND it MUST run `php artisan migrate --force` inside the `query` container
- AND it MUST run `php artisan localstack:setup` inside the `importer` container

#### Scenario: Script exit codes

- GIVEN `init.sh` is executed
- WHEN any migration or setup command fails
- THEN the script MUST exit with a non-zero status code
- AND it MUST print the failing command's output to stderr

### REQ-DEV-009: Init Script Idempotency

The `init.sh` script MUST be safe to run multiple times without side effects.

#### Scenario: Repeated execution

- GIVEN `init.sh` has already been executed successfully once
- WHEN `init.sh` is executed again
- THEN migrations MUST complete without errors (Laravel migrations are idempotent)
- AND `localstack:setup` MUST complete without errors (idempotent by design)
- AND the script MUST exit with status code 0

### REQ-DEV-010: Healthchecks for All Services

All services in `docker-compose.yml` MUST have healthcheck definitions.

#### Scenario: Application service healthchecks

- GIVEN `docker-compose.yml` is configured
- WHEN the `importer` and `query` services are inspected
- THEN both MUST define a `healthcheck` block with an HTTP or TCP check against their respective ports

#### Scenario: Infrastructure healthchecks already defined

- GIVEN `docker-compose.yml` is configured
- WHEN `importer-db`, `query-db`, and `localstack` are inspected
- THEN they MUST retain their existing healthcheck definitions (pg_isready / curl)

## ADDED Requirements

None — all requirements for this domain are modifications to existing REQ-DEV-001 or new sequential IDs extending the existing spec.
