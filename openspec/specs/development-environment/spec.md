# Development Environment Specification

## Purpose

Define the Docker Compose local development environment with PostgreSQL per-service databases, LocalStack for AWS service emulation (S3 + SQS), and both Laravel application services.

## Requirements

### REQ-DEV-001: Docker Compose Service Definitions

The system MUST define exactly 5 services in `docker-compose.yml`: `importer-service`, `query-service`, `importer-db`, `query-db`, and `localstack`.

#### Scenario: All services start successfully

- GIVEN a valid `docker-compose.yml` exists at project root
- WHEN `docker-compose up -d` is executed
- THEN all 5 services reach a healthy state
- AND `docker-compose ps` shows 5 running containers

#### Scenario: Service isolation

- GIVEN both services are running
- WHEN `importer-service` connects to a database
- THEN it MUST connect only to `importer-db`
- AND `query-service` MUST connect only to `query-db`

### REQ-DEV-002: PostgreSQL Database Configuration

Each application service MUST have a dedicated PostgreSQL 18 instance with independent data volumes.

#### Scenario: Database persistence

- GIVEN `importer-db` has stored data
- WHEN `docker-compose down` is executed (without `-v`)
- AND `docker-compose up -d` is re-executed
- THEN previously stored data remains intact

#### Scenario: Database port mapping

- GIVEN `docker-compose.yml` is configured
- WHEN services are running
- THEN `importer-db` MUST be accessible on host port 5432
- AND `query-db` MUST be accessible on host port 5433

### REQ-DEV-003: LocalStack AWS Emulation

The system MUST provide LocalStack 4.14 with S3 and SQS services enabled for local development.

#### Scenario: S3 bucket creation

- GIVEN LocalStack is running
- WHEN an S3 bucket is created via AWS CLI with `--endpoint-url=http://localhost:4566`
- THEN the bucket is accessible for read/write operations

#### Scenario: SQS queue creation

- GIVEN LocalStack is running
- WHEN an SQS queue is created via AWS CLI with `--endpoint-url=http://localhost:4566`
- THEN messages can be sent to and received from the queue

#### Scenario: LocalStack port exposure

- GIVEN LocalStack is running
- WHEN a client connects to `http://localhost:4566`
- THEN the connection succeeds for S3 and SQS API calls

### REQ-DEV-004: Application Service Ports

The `query-service` MUST be accessible on host port 8000 and `importer-service` on host port 8001.

#### Scenario: Port availability

- GIVEN both application services are running
- WHEN a client sends an HTTP request to `http://localhost:8000`
- THEN the `query-service` responds
- AND a request to `http://localhost:8001` reaches `importer-service`

### REQ-DEV-005: Named Volumes for Data Persistence

The system MUST use named Docker volumes for database data and LocalStack state.

#### Scenario: Volume definitions

- GIVEN `docker-compose.yml` defines volumes
- WHEN inspected
- THEN `importer-db-data`, `query-db-data`, and `localstack-data` volumes MUST be declared

### REQ-DEV-006: Environment Variable Configuration

The system MUST provide `.env.example` files at root level and within each service directory.

#### Scenario: Root environment file

- GIVEN the project root contains `.env.example`
- WHEN inspected
- THEN it MUST include variables for `COMPOSE_PROJECT_NAME`, `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`

#### Scenario: Service environment files

- GIVEN each service directory contains `.env.example`
- WHEN inspected
- THEN they MUST include `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- AND service-specific variables for SQS/S3 endpoints
