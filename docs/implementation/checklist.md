# Implementation Checklist

> Recommended execution order. Each item is an atomic task.

## Phase 0 – Setup

- [ ] Initialize Git repository with appropriate `.gitignore` for Laravel
- [ ] Create repository directory structure (see [Infrastructure](../architecture/infrastructure.md))
- [ ] Create `docker-compose.yml` with services `importer-db`, `query-db` and `localstack`
- [ ] Create `importer-service` as Laravel 13 project (`composer create-project`)
- [ ] Create `query-api` as Laravel 13 project
- [ ] Configure `.env.example` for both services

## Phase 1 – Database

- [ ] Create `create_import_logs_table` migration in importer-service
- [ ] Create `create_deudores_table` migration in query-api
- [ ] Create `create_entidades_table` migration in query-api
- [ ] Create `ImportLog` model in importer-service
- [ ] Create `Deudor` model in query-api
- [ ] Create `Entity` model in query-api
- [ ] Verify `php artisan migrate` runs without errors in both DB containers

## Phase 2 – Importer: Domain Layer

- [ ] Create `Situacion` Value Object with valid code validation ('01', '03', '04', '05', '11', '21', '23')
- [ ] Create `Monto` Value Object with BCRA format parsing (comma → period)
- [ ] Create `Deudor` Entity with properties: `nro_identificacion`, `situacion_maxima`, `suma_total_prestamos`
- [ ] Create `Entity` Entity with properties: `codigo_entidad`, `suma_total_prestamos`
- [ ] Create Domain Event `DeudorProcessed` with payload DTO
- [ ] Create Domain Event `EntityProcessed` with payload DTO
- [ ] Create Domain Event `ImportCompleted` with payload DTO

## Phase 3 – Importer: Application Layer (Parser)

- [ ] Create DTO `BcraRecordDTO` with relevant BCRA file fields
- [ ] Create `BcraFileParser` that receives `SplFileObject` and returns `LazyCollection<BcraRecordDTO>`
- [ ] Implement ISO-8859-1 → UTF-8 encoding conversion per line
- [ ] Implement field extraction by fixed position (see [File Format](../architecture/file-format.md))
- [ ] Implement filter: only records with `tipo_identificacion = '11'` (RN-04)
- [ ] Implement filter: only records with `situacion` in valid codes (RN-05)
- [ ] Implement amount parsing: replace comma with period, convert to float (RN-07)
- [ ] Write unit test for `BcraFileParser` with 10-line fixture

## Phase 4 – Importer: Application Layer (Transformer)

- [ ] Create `BcraDataTransformer` that receives `LazyCollection<BcraRecordDTO>`
- [ ] Implement grouping by `nro_identificacion` → calculate `MAX(situacion)` and `SUM(monto)` (RN-01)
- [ ] Implement grouping by `codigo_entidad` → calculate `SUM(monto)` (RN-02)
- [ ] Return two collections: `Collection<Deudor>` and `Collection<Entity>`
- [ ] Write unit test for `BcraDataTransformer` with known test data

## Phase 5 – Importer: Infrastructure Layer (Messaging)

- [ ] Create `EventPublisher` interface with method `publish(DomainEvent $event): void`
- [ ] Create `SqsEventPublisher` implementation that publishes to SQS using `aws/aws-sdk-php`
- [ ] Create artisan command `localstack:setup` that creates S3 bucket and SQS queues in LocalStack
- [ ] Write integration test for `SqsEventPublisher` with LocalStack

## Phase 6 – Importer: Infrastructure Layer (Notification)

- [ ] Create `NotificationSender` interface with method `send(ImportCompleted $event): void`
- [ ] Create `LogNotification` implementation that writes structured JSON with `Log::info()`
- [ ] Create `WebhookNotification` implementation that does `Http::post()` to configured URL
- [ ] Create `SqsNotification` implementation that publishes to notifications queue
- [ ] Create `NotificationFactory` that returns sender based on `NOTIFICATION_DRIVER`

## Phase 7 – Importer: Orchestration

- [ ] Create `ImportOrchestrator` that coordinates: Parser → Transformer → Publish Events → Notify
- [ ] Implement process tracking in `ImportLog` (status, times, counts)
- [ ] Create `ProcessBcraFile` Job that uses `ImportOrchestrator`
- [ ] Create `PublishDeudorEvents` Job that publishes debtor events to SQS in batches
- [ ] Create `PublishEntityEvents` Job that publishes entity events to SQS in batches
- [ ] Create `ProcessBcraFileCommand` artisan that accepts `{path}` and dispatches the Job
- [ ] Create `UploadController@store` that validates file, saves to storage, and dispatches Job
- [ ] Register route `POST /upload` in `routes/api.php`

## Phase 8 – Query API: Event Handlers

- [ ] Create `UpsertDeudorHandler` that consumes `DeudorProcessed` and upserts in query DB
- [ ] Create `UpsertEntityHandler` that consumes `EntityProcessed` and upserts in query DB
- [ ] Create `LogImportCompletionHandler` that consumes `ImportCompleted` and logs
- [ ] Configure Laravel Queues with SQS driver to consume events
- [ ] Write integration test for handlers with LocalStack

## Phase 9 – Query API: Endpoints

- [ ] Create `DeudorController` with methods: `show`, `top`, `index`
- [ ] Create `EntidadController` with method `show`
- [ ] Implement `GET /deudores/{cuit}` with CUIT format validation (11 digits)
- [ ] Implement `GET /entidades/{codigo}` with code validation
- [ ] Implement `GET /deudores/top/{n}` with n validation (1–1000)
- [ ] Implement `GET /deudores` with optional filter `?situacion=X` and pagination
- [ ] Create `DeudorResource` and `EntidadResource` with structure from [API Contracts](../architecture/api-contracts.md)
- [ ] Create Form Requests for input validation
- [ ] Register all routes in `routes/api.php`
- [ ] Write Feature tests for each endpoint (happy path + 404 + 422)

## Phase 10 – Dockerization

- [ ] Create `Dockerfile` for `importer-service` (see [Infrastructure](../architecture/infrastructure.md))
- [ ] Create `Dockerfile` for `query-api`
- [ ] Add `importer`, `query-api` and `query-worker` to `docker-compose.yml`
- [ ] Verify `docker-compose up` starts all services without errors
- [ ] Verify migrations run correctly in Docker environment
- [ ] Create `init.sh` script that runs migrations + LocalStack setup on startup

## Phase 11 – Documentation

- [ ] Write `README.md` with: prerequisites, setup instructions, endpoints, environment variables
- [ ] Document expected BCRA file format (reference to parsed fields)
- [ ] Include `curl` usage examples for each endpoint
- [ ] Document event-driven architecture and data flow
