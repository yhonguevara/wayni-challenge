# Infrastructure & Docker

## Directory Structure

```
/
├── docker-compose.yml
├── .env.example
├── services/
│   ├── importer/
│   │   ├── Dockerfile
│   │   ├── .env.example
│   │   └── (Laravel app with Clean Architecture)
│   │       ├── app/
│   │       │   ├── Console/Commands/
│   │       │   │   └── ProcessBcraFileCommand.php
│   │       │   ├── Http/Controllers/
│   │       │   │   └── UploadController.php
│   │       │   ├── Jobs/
│   │       │   │   ├── ProcessBcraFile.php
│   │       │   │   ├── PublishDebtorEvents.php
│   │       │   │   └── PublishEntityEvents.php
│   │       │   ├── Domain/
│   │       │   │   ├── Import/
│   │       │   │   │   └── ImportLog.php (Eloquent model)
│   │       │   │   ├── Debtor/
│   │       │   │   │   ├── Debtor.php (Entity)
│   │       │   │   │   ├── DebtorCollection.php (Value Object)
│   │       │   │   │   └── Events/DebtorProcessed.php (Domain Event)
│   │       │   │   └── Entity/
│   │       │   │       ├── Entity.php (Entity)
│   │       │   │       └── Events/EntityProcessed.php (Domain Event)
│   │       │   ├── Application/
│   │       │   │   ├── DTOs/
│   │       │   │   │   ├── BcraRecordDTO.php
│   │       │   │   │   ├── DebtorDTO.php
│   │       │   │   │   └── EntityDTO.php
│   │       │   │   ├── Services/
│   │       │   │   │   ├── BcraFileParser.php
│   │       │   │   │   ├── BcraDataTransformer.php
│   │       │   │   │   └── ImportOrchestrator.php
│   │       │   │   └── Events/
│   │       │   │       └── ImportCompleted.php
│   │       │   └── Infrastructure/
│   │       │       ├── Persistence/
│   │       │       │   └── EloquentImportLogRepository.php
│   │       │       ├── Messaging/
│   │       │       │   ├── SqsEventPublisher.php
│   │       │       │   └── S3FileUploader.php
│   │       │       └── Notification/
│   │       │           ├── LogNotification.php
│   │       │           ├── WebhookNotification.php
│   │       │           └── SqsNotification.php
│   │       └── tests/
│   │           ├── Unit/
│   │           │   ├── BcraFileParserTest.php
│   │           │   └── BcraDataTransformerTest.php
│   │           └── Feature/
│   │               └── UploadControllerTest.php
│   └── query/
│       ├── Dockerfile
│       ├── .env.example
│       └── (Laravel app with Clean Architecture)
│           ├── app/
│           │   ├── Http/
│           │   │   ├── Controllers/
│           │   │   │   ├── DebtorController.php
│           │   │   │   └── EntityController.php
│           │   │   ├── Resources/
│           │   │   │   ├── DebtorResource.php
│           │   │   │   └── EntityResource.php
│           │   │   └── Requests/
│           │   │       ├── TopDebtorsRequest.php
│           │   │       └── ListDebtorsRequest.php
│           │   ├── Domain/
│           │   │   ├── Debtor/
│           │   │   │   └── Debtor.php (Eloquent model)
│           │   │   └── Entity/
│           │   │       └── Entity.php (Eloquent model)
│           │   ├── Application/
│           │   │   ├── Handlers/
│           │   │   │   ├── UpsertDebtorHandler.php
│           │   │   │   ├── UpsertEntityHandler.php
│           │   │   │   └── LogImportCompletionHandler.php
│           │   │   └── Queries/
│           │   │       ├── GetDebtorByCuit.php
│           │   │       ├── GetEntityByCode.php
│           │   │       ├── GetTopDebtors.php
│           │   │       └── ListDebtors.php
│           │   └── Infrastructure/
│           │       └── Messaging/
│           │           └── SqsEventConsumer.php (Laravel Queue Worker)
│           └── tests/
│               ├── Unit/
│               │   ├── UpsertDebtorHandlerTest.php
│               │   └── GetTopDebtorsTest.php
│               └── Feature/
│                   ├── DebtorControllerTest.php
│                   └── EntityControllerTest.php
├── infrastructure/
│   ├── template.yaml          # AWS SAM template
│   └── samconfig.toml         # SAM deployment config
└── docs/
    ├── architecture/
    └── conventions/
```

---

## `docker-compose.yml`

```yaml
services:
  importer-db:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB:       ${IMPORTER_DB_DATABASE}
      POSTGRES_USER:     ${IMPORTER_DB_USERNAME}
      POSTGRES_PASSWORD: ${IMPORTER_DB_PASSWORD}
    volumes:
      - importer_pgdata:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${IMPORTER_DB_USERNAME}"]
      interval: 5s
      retries: 5

  query-db:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB:       ${QUERY_DB_DATABASE}
      POSTGRES_USER:     ${QUERY_DB_USERNAME}
      POSTGRES_PASSWORD: ${QUERY_DB_PASSWORD}
    volumes:
      - query_pgdata:/var/lib/postgresql/data
    ports:
      - "5433:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${QUERY_DB_USERNAME}"]
      interval: 5s
      retries: 5

  localstack:
    image: localstack/localstack:4.14
    environment:
      SERVICES: s3,sqs
      DEFAULT_REGION: us-east-1
    ports:
      - "4566:4566"
    volumes:
      - localstack_data:/var/lib/localstack

  importer:
    build: ./services/importer
    ports:
      - "8001:8000"
    env_file:
      - ./services/importer/.env
    depends_on:
      importer-db:
        condition: service_healthy
      localstack:
        condition: service_started

  query:
    build: ./services/query
    ports:
      - "8000:8000"
    env_file:
      - ./services/query/.env
    depends_on:
      query-db:
        condition: service_healthy
      localstack:
        condition: service_started

  query-worker:
    build: ./services/query
    command: php artisan queue:work sqs --sleep=3 --tries=3 --max-time=3600
    env_file:
      - ./services/query/.env
    depends_on:
      query-db:
        condition: service_healthy
      localstack:
        condition: service_started

volumes:
  importer_pgdata:
  query_pgdata:
  localstack_data:
```

---

## `Dockerfile` (base for both services)

```dockerfile
FROM php:8.5-cli-alpine

RUN apk add --no-cache git curl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

---

## AWS SAM Template (`infrastructure/template.yaml`)

Production infrastructure is defined as code using AWS SAM. The template includes:

| Resource | Type | Key Config |
|----------|------|------------|
| `BcraFilesBucket` | S3 Bucket | Versioning, SSE-S3, CORS for pre-signed URLs |
| `DebtorEventsQueue` | SQS Queue | VisibilityTimeout 300s, DLQ maxReceiveCount=3 |
| `EntityEventsQueue` | SQS Queue | Same config as debtor queue |
| `ImporterTaskDef` | ECS Task Definition | Fargate, 4096 CPU, 8192 Memory, awsvpc |
| `QueryTaskDef` | ECS Task Definition | Same as importer |
| `QueryHttpApi` | HTTP API | Routes to query ECS service |
| `EcsTaskExecutionRole` | IAM Role | ECR pull + CloudWatch logs |
| `EcsTaskRole` | IAM Role | S3 read/write + SQS send/receive |

### SAM Deployment

```bash
# Validate template
sam validate --lint

# Deploy
sam deploy --guided
```

Config is in `infrastructure/samconfig.toml`.

---

## Monorepo Structure

```
wayni-challenge/
├── services/
│   ├── importer/          # Write side (file processing)
│   └── query/             # Read side (API queries)
├── infrastructure/        # AWS SAM IaC
├── docs/                  # Architecture & conventions
├── openspec/              # SDD artifacts
├── docker-compose.yml     # Local dev environment
└── .env.example           # Root env vars
```

---

*Last updated: Phase 0 SDD*
