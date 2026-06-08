# Docker E2E Specification

## Purpose

Define end-to-end verification requirements ensuring the complete system works as an integrated unit: all services start, initialization runs, file processing flows through the pipeline, and query endpoints return correct data.

## Requirements

### REQ-E2E-001: Full Service Startup

`docker-compose up -d` MUST start all 6 services without errors.

#### Scenario: All containers reach healthy state

- GIVEN a clean environment with no existing containers or volumes
- WHEN `docker-compose up -d` is executed
- THEN `importer-db`, `query-db`, and `localstack` MUST become healthy
- AND `importer`, `query`, and `query-worker` MUST be running
- AND `docker-compose ps` MUST show 6 services

#### Scenario: No error logs on startup

- GIVEN all 6 services have started
- WHEN `docker-compose logs` is inspected within 60 seconds
- THEN no service MUST contain fatal errors or crash-loop restarts

### REQ-E2E-002: Init Script Execution

`init.sh` MUST be executable and runnable from the host after services are up.

#### Scenario: Init script runs after docker-compose up

- GIVEN all 6 services are running
- WHEN `bash init.sh` is executed from the project root
- THEN migrations MUST complete for both `importer` and `query` databases
- AND LocalStack resources (S3 bucket, SQS queues) MUST be created
- AND the script MUST exit with status code 0

#### Scenario: Init script is executable

- GIVEN the project root is inspected
- WHEN `init.sh` file permissions are checked
- THEN the file MUST have execute permission (`chmod +x`)

### REQ-E2E-003: Upload Flow End-to-End

POST `/upload` with a valid BCRA file MUST trigger processing and make data queryable through the query API.

#### Scenario: Upload and query cycle

- GIVEN all services are running and `init.sh` has completed
- WHEN a valid BCRA TXT file is uploaded via `POST http://localhost:8001/upload`
- THEN the importer MUST parse the file and publish events to SQS
- AND the `query-worker` MUST consume events and upsert data
- AND `GET http://localhost:8000/debtors/{cuit}` MUST return the processed debtor within 30 seconds

#### Scenario: Upload with invalid file

- GIVEN all services are running
- WHEN a non-TXT file is uploaded via `POST http://localhost:8001/upload`
- THEN the importer MUST return HTTP 422 with a validation error message

### REQ-E2E-004: Artisan Command Processing

`php artisan bcra:process /path/to/file` MUST work inside the importer container.

#### Scenario: Artisan command processes file

- GIVEN all services are running and `init.sh` has completed
- WHEN `docker-compose exec importer php artisan bcra:process /app/storage/deudores_bcra.txt` is executed
- THEN the command MUST exit with status code 0
- AND it MUST output processing summary (total lines, debtors, entities, duration)

#### Scenario: Artisan command with missing file

- GIVEN all services are running
- WHEN `docker-compose exec importer php artisan bcra:process /nonexistent.txt` is executed
- THEN the command MUST exit with a non-zero status code
- AND it MUST output an error message indicating the file was not found

### REQ-E2E-005: Query API Returns Processed Data

After processing, all query API endpoints MUST return correct data.

#### Scenario: Debtor lookup by CUIT

- GIVEN a BCRA file has been processed with known test data
- WHEN `GET http://localhost:8000/debtors/{valid-cuit}` is called
- THEN the response MUST include `nro_identificacion`, `situacion_maxima`, and `suma_total_prestamos`
- AND HTTP status MUST be 200

#### Scenario: Entity lookup by code

- GIVEN a BCRA file has been processed with known test data
- WHEN `GET http://localhost:8000/entities/{valid-code}` is called
- THEN the response MUST include `codigo_entidad` and `suma_total_prestamos`
- AND HTTP status MUST be 200

#### Scenario: Top debtors endpoint

- GIVEN a BCRA file has been processed
- WHEN `GET http://localhost:8000/debtors/top/5` is called
- THEN the response MUST return at most 5 debtors ordered by `suma_total_prestamos` descending
- AND HTTP status MUST be 200

#### Scenario: Non-existent debtor

- GIVEN a BCRA file has been processed
- WHEN `GET http://localhost:8000/debtors/00000000000` is called
- THEN HTTP status MUST be 404
