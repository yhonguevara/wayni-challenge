# Wayni BCRA Deudores Processor

[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4)](https://php.net)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791)](https://postgresql.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)

Microservices-based system for processing the BCRA (Central Bank of Argentina) debtor registry file. Implements CQRS, event-driven architecture, and Clean Architecture with DDD tactical patterns.

## Architecture

```
                        ┌─────────────────────────────────────────────────────┐
                        │                 Docker Compose                      │
                        │                                                     │
  deudores_bcra.txt     │  ┌──────────────┐    SQS     ┌──────────────────┐  │
  ─────────────────────►│  │   Importer   │───────────►│  Query Worker    │  │
  POST /upload          │  │  (port 8001) │  3 queues  │  (queue:work)    │  │
                        │  └──────┬───────┘            └────────┬─────────┘  │
                        │         │                             │            │
                        │  ┌──────▼───────┐            ┌────────▼─────────┐  │
                        │  │ Importer DB  │            │    Query DB      │  │
                        │  │ (PostgreSQL) │            │   (PostgreSQL)   │  │
                        │  └──────────────┘            └──────────────────┘  │
                        │                                           │       │
                        │                              ┌────────────▼─────┐  │
                        │                              │    Query API     │  │
                        │                              │   (port 8000)    │  │
                        │                              └──────────────────┘  │
                        │                                                     │
                        │  ┌──────────────┐  ┌──────────────┐                │
                        │  │  LocalStack  │  │     S3       │                │
                        │  │  (SQS + S3)  │  │   (files)    │                │
                        │  └──────────────┘  └──────────────┘                │
                        └─────────────────────────────────────────────────────┘
```

**Services:**
- **Importer** (Write Side) — Parses BCRA TXT file, publishes domain events to SQS, stores files in S3
- **Query API** (Read Side) — REST API for querying debtors and entities from the read model
- **Query Worker** — Consumes SQS events and upserts data into the query database

**Communication:** Asynchronous via SQS (3 queues: `debtor-events`, `entity-events`, `import-completed`)

**Database:** Database-per-service pattern — each service owns its PostgreSQL instance

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| Database | PostgreSQL 18 |
| Container | Docker Compose |
| AWS Simulation | LocalStack 4.14 (SQS/S3) |
| IaC | AWS SAM |
| Architecture | Microservices · CQRS · Event-Driven · Clean Architecture · DDD |

## Quick Start

```bash
# 1. Start all services
docker compose up -d

# 2. Run migrations and setup LocalStack
./init.sh

# 3. Verify services are healthy
curl http://localhost:8001/up && echo "" && curl http://localhost:8000/up
```

**Prerequisites:** Docker, Docker Compose, and the BCRA data file (`deudores_bcra.txt`).

## API Endpoints

### Importer Service (port 8001)

#### Upload File

```bash
# Multipart form upload
curl -X POST http://localhost:8001/upload \
  -F "file=@deudores_bcra.txt"

# Response: 202 Accepted
# {"message":"File received. Processing started.","import_log_id":42}
```

#### Pre-Signed URL Upload (S3)

```bash
# Get pre-signed URL
curl -X POST http://localhost:8001/api/presign \
  -H "Content-Type: application/json" \
  -d '{"filename": "deudores.txt"}'

# Upload to S3 using returned URL
curl -X PUT "<upload_url>" -H "Content-Type: text/plain" --data-binary @deudores_bcra.txt

# Notify upload completion
curl -X POST http://localhost:8001/api/notify-upload \
  -H "Content-Type: application/json" \
  -d '{"key": "uploads/deudores.txt", "size": 12345}'
```

### Query API (port 8000)

#### Get Debtor by CUIT

```bash
curl http://localhost:8000/debtors/20123456789

# Response: 200 OK
# {"data":{"identificationNumber":"20123456789","maxSituation":"03","totalLoanAmount":"1250.00"}}
```

#### Get Entity by Code

```bash
curl http://localhost:8000/entities/00011

# Response: 200 OK
# {"data":{"entityCode":"00011","totalLoanAmount":"98430.00"}}
```

#### Top N Debtors by Loan Amount

```bash
curl http://localhost:8000/debtors/top/10

# Response: 200 OK
# {"data":[...],"meta":{"count":10}}
```

#### List Debtors with Filters

```bash
# Filter by situation code
curl "http://localhost:8000/debtors?situation=03&per_page=50"

# Response: 200 OK
# {"data":[...],"meta":{"current_page":1,"per_page":50,"total":1240}}
```

**Situation codes:** `01` (normal), `03` (with observation), `04` (non-compliant), `05` (deficient), `11` (doubtful), `21` (irrecoverable), `23` (irrecoverable - judicial)

## Processing Files

### Via Upload Endpoint

```bash
curl -X POST http://localhost:8001/upload -F "file=@deudores_bcra.txt"
```

### Via Artisan Command

```bash
# Copy file into container
docker compose cp deudores_bcra.txt importer:/tmp/

# Process file
docker compose exec importer php artisan bcra:process /tmp/deudores_bcra.txt
```

The command outputs a processing summary with total lines, debtors, entities, and duration.

## Testing

```bash
# Run importer tests
docker compose exec importer php artisan test

# Run query API tests
docker compose exec query php artisan test
```

Tests include unit tests for domain logic, feature tests for API endpoints, and integration tests for event handling.

## Project Structure

```
wayni-challenge/
├── services/
│   ├── importer/              # Write-side microservice
│   │   ├── app/
│   │   │   ├── Domain/        # Entities, Value Objects, Events
│   │   │   ├── Application/   # Use Cases, DTOs
│   │   │   ├── Infrastructure/# Eloquent, SQS, S3, File Parser
│   │   │   └── Presentation/  # Controllers, API Resources
│   │   ├── database/migrations/
│   │   ├── Dockerfile
│   │   └── .env
│   └── query/                 # Read-side microservice
│       ├── app/
│       │   ├── Domain/
│       │   ├── Application/
│       │   ├── Infrastructure/
│       │   └── Presentation/
│       ├── database/migrations/
│       ├── Dockerfile
│       └── .env
├── infrastructure/
│   ├── template.yaml          # AWS SAM template
│   └── samconfig.toml         # SAM deployment config
├── docs/
│   └── architecture/          # Architecture documentation
├── docker-compose.yml
├── init.sh                    # Bootstrap script
└── README.md
```

## SAM Deployment (AWS)

For deploying to real AWS infrastructure:

### Prerequisites

- [AWS CLI](https://aws.amazon.com/cli/) configured with credentials
- [AWS SAM CLI](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/install-sam-cli.html)
- Existing VPC with private subnets (NAT gateway required)
- ECR repositories for importer and query images

### Build and Push Images

```bash
# Build images
docker build -t wayni-importer ./services/importer
docker build -t wayni-query ./services/query

# Authenticate to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin <account-id>.dkr.ecr.us-east-1.amazonaws.com

# Tag and push
docker tag wayni-importer:latest <account-id>.dkr.ecr.us-east-1.amazonaws.com/wayni-importer:latest
docker tag wayni-query:latest <account-id>.dkr.ecr.us-east-1.amazonaws.com/wayni-query:latest
docker push <account-id>.dkr.ecr.us-east-1.amazonaws.com/wayni-importer:latest
docker push <account-id>.dkr.ecr.us-east-1.amazonaws.com/wayni-query:latest
```

### Deploy

```bash
cd infrastructure

# Validate template
sam validate --lint

# Deploy (guided - first time)
sam deploy --guided

# Deploy (subsequent)
sam deploy
```

### Required Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `Environment` | Deployment environment | `dev`, `stg`, `prod` |
| `VpcId` | VPC for ECS tasks | `vpc-0abc1234` |
| `SubnetIds` | Private subnets with NAT | `subnet-0abc,subnet-0def` |
| `ImporterImageUri` | ECR image for importer | `123456.dkr.ecr.../wayni-importer:latest` |
| `QueryImageUri` | ECR image for query | `123456.dkr.ecr.../wayni-query:latest` |

## Troubleshooting

### Services not starting

```bash
# Check service status
docker compose ps

# Check logs for errors
docker compose logs importer
docker compose logs query
docker compose logs query-worker
```

### Migrations failing

```bash
# Ensure databases are healthy
docker compose exec importer-db pg_isready -U wayni
docker compose exec query-db pg_isready -U wayni

# Run migrations manually
docker compose exec importer php artisan migrate --force
docker compose exec query php artisan migrate --force
```

### LocalStack not ready

```bash
# Check LocalStack health
curl http://localhost:4566/_localstack/health

# Restart LocalStack
docker compose restart localstack

# Re-run setup
docker compose exec importer php artisan localstack:setup
```

### Queue worker not consuming

```bash
# Check worker logs
docker compose logs -f query-worker

# Verify queues exist
docker compose exec importer php artisan tinker
>>> app(Aws\Sqs\SqsClient::class)->listQueues(['QueueNamePrefix' => ''])->get('QueueUrls')

# Restart worker
docker compose restart query-worker
```

### Re-run full setup

```bash
docker compose down -v
docker compose up -d
./init.sh
```

## License

MIT
