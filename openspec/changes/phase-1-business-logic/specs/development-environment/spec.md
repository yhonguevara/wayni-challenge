# Delta for Development Environment

## MODIFIED Requirements

### REQ-DEV-001: Docker Compose Service Definitions

The system MUST define exactly 5 services in `docker-compose.yml`: `importer`, `query`, `importer-db`, `query-db`, and `localstack`.

(Previously: Services were named `importer-service` and `query-service`)

#### Scenario: All services start successfully

- GIVEN a valid `docker-compose.yml` exists at project root
- WHEN `docker-compose up -d` is executed
- THEN all 5 services reach a healthy state
- AND `docker-compose ps` shows 5 running containers

#### Scenario: Service isolation

- GIVEN both services are running
- WHEN `importer` connects to a database
- THEN it MUST connect only to `importer-db`
- AND `query` MUST connect only to `query-db`

### REQ-DEV-004: Application Service Ports

The `query` service MUST be accessible on host port 8000 and `importer` on host port 8001.

(Previously: References used `query-service` and `importer-service`)

#### Scenario: Port availability

- GIVEN both application services are running
- WHEN a client sends an HTTP request to `http://localhost:8000`
- THEN the `query` service responds
- AND a request to `http://localhost:8001` reaches `importer`
