# Wayni BCRA Deudores Processor

[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4)](https://php.net)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791)](https://postgresql.org)
[![Tests](https://img.shields.io/badge/Tests-193%20passing-brightgreen)]()

Microservices system that processes the BCRA (Central Bank of Argentina) debtor registry file (~6 GB TXT). Parses, transforms, aggregates, and persists data with event-driven CQRS architecture.

## Architecture

```
                          ┌──────────────────────────────────────────────────────────┐
                          │                    Docker Compose                        │
                          │                                                          │
  deudores_bcra.txt       │  ┌────────────────┐     SQS      ┌───────────────────┐  │
  ──────────────────────► │  │   Importer     │─────────────►│ Importer Consumer │  │
  POST /upload            │  │  (port 8001)   │  3 queues    │ (events:consume)  │  │
  or artisan command      │  └───────┬────────┘              └────────┬──────────┘  │
                          │          │                                │              │
                          │  ┌───────▼────────┐              ┌───────▼──────────┐   │
                          │  │Importer Worker │              │   Shared DB      │   │
                          │  │ (queue:work)   │              │  (PostgreSQL)    │   │
                          │  └────────────────┘              └───────┬──────────┘   │
                          │                                          │              │
                          │                                 ┌────────▼─────────┐    │
                          │                                 │    Query API     │    │
                          │                                 │  (port 8000)     │    │
                          │                                 │  (read-only)     │    │
                          │                                 └──────────────────┘    │
                          │                                                          │
                          │  ┌──────────────┐  ┌──────────────┐                      │
                          │  │  LocalStack  │  │     S3       │                      │
                          │  │  (SQS + S3)  │  │   (files)    │                      │
                          │  └──────────────┘  └──────────────┘                      │
                          └──────────────────────────────────────────────────────────┘
```

### Services

| Service | Role | Command |
|---------|------|---------|
| **Importer** | HTTP API — receives files, dispatches processing jobs | `php artisan serve` |
| **Importer Worker** | Processes Laravel queue jobs — parses TXT, publishes events to SQS | `php artisan queue:work` |
| **Importer Consumer** | Consumes SQS events — upserts debtors/entities, fires completion notification | `php artisan events:consume` |
| **Query API** | Read-only REST API — queries debtors and entities | `php artisan serve` |
| **Shared DB** | Single PostgreSQL instance — importer writes, query reads | — |
| **LocalStack** | Simulates AWS SQS and S3 locally | — |

### Key Design Decisions

- **Shared database** — one PostgreSQL instance; importer is the sole writer, query is read-only
- **Completion sentinel** — notification fires exactly once, only after ALL records are persisted (not just enqueued)
- **Processed-events ledger** — idempotent increments under SQS at-least-once delivery
- **S3 direct upload** — multi-GB files go straight to S3 via pre-signed URL, never through PHP

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| Database | PostgreSQL 18 |
| Container | Docker Compose |
| AWS Simulation | LocalStack 4.14 (SQS + S3) |
| Architecture | Microservices, CQRS, Event-Driven, Clean Architecture, DDD |

## Quick Start

```bash
# Clone and start
git clone <repo-url> && cd wayni-challenge
docker compose up -d

# Wait ~30s for init to complete, then verify
docker compose ps
```

That's it. The `init` service automatically runs migrations, creates the test database, and sets up LocalStack (S3 bucket + SQS queues).

### Verify services are healthy

```bash
curl -s http://localhost:8001/up && echo "" && curl -s http://localhost:8000/up
```

## Processing a File

> **⚠️ IMPORTANT:** The real BCRA file is ~6 GB. Due to performance and local capacity constraints, **the original file can only be processed using Option B** (copy to container + artisan command). Options A and C are provided for testing with smaller files or API integration purposes.

### Option A: S3 pre-signed URL (for browser/client uploads)

```bash
# 1. Get pre-signed upload URL
curl -s -X POST http://localhost:8001/api/presign \
  -H "Content-Type: application/json" \
  -d '{"filename": "deudores.txt"}' | jq .

# 2. Upload directly to S3 (using returned fields)
curl -X POST "<upload_url>" \
  -F "key=<key>" \
  -F "file=@deudores_bcra.txt" \
  ... (other fields from step 1)

# 3. Notify completion
curl -X POST http://localhost:8001/api/notify-upload \
  -H "Content-Type: application/json" \
  -d '{"key": "<key>"}'
```

### Option B: Copy file into container (REQUIRED for the 6 GB BCRA file)

```bash
# Copy the file into the importer container
docker compose cp deudores_bcra.txt importer:/app/storage/app/uploads/

# Process it
docker compose exec importer php artisan bcra:process /app/storage/app/uploads/deudores_bcra.txt
```

The command streams a processing summary: total lines, debtors, entities, and duration.

### Option C: Multipart upload (small files only)

```bash
curl -X POST http://localhost:8001/upload -F "file=@small_file.txt"
```

## Web Interface

The importer service provides a web interface for file upload and API testing:

| URL | Description |
|-----|-------------|
| http://localhost:8001/ | Redirects to upload page |
| http://localhost:8001/upload | File upload interface (Mode A: S3 pre-signed, Mode B: local path) |
| http://localhost:8001/panel | API testing panel — test all Query API endpoints interactively |

## API Endpoints

### Importer (port 8001)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/upload` | Upload file via multipart (small files) |
| `POST` | `/api/presign` | Get S3 pre-signed URL for direct upload |
| `POST` | `/api/notify-upload` | Notify that S3 upload completed |

### Query API (port 8000)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/debtors/{cuit}` | Get debtor by CUIT |
| `GET` | `/debtors/top/{n}` | Top N debtors by total loan amount |
| `GET` | `/debtors?situation={code}` | List debtors with filters and pagination |
| `GET` | `/entities/{code}` | Get entity by code |

### Examples

```bash
# Get debtor by CUIT
curl -s http://localhost:8000/debtors/20123456789 | jq .

# Top 10 debtors
curl -s http://localhost:8000/debtors/top/10 | jq .

# Filter by situation code
curl -s "http://localhost:8000/debtors?situation=05&per_page=50" | jq .

# Get entity
curl -s http://localhost:8000/entities/00011 | jq .
```

**Situation codes:** `01` normal, `03` with observation, `04` non-compliant, `05` deficient, `11` doubtful, `21` irrecoverable, `23` irrecoverable (judicial)

## Testing

```bash
# Importer: 175 tests (unit + feature + integration)
docker compose exec importer php artisan test

# Query API: 18 tests
docker compose exec query php artisan test
```

**Total: 193 tests, 456 assertions, 0 failures.**

Each service uses its own isolated test database (`wayni_importer_test` and `wayni_query_test`) to prevent schema collisions when running `RefreshDatabase`.

Tests cover:
- Domain value objects and business rules (situation severity, amount parsing, CUIT validation)
- File parser (ISO-8859-1 encoding, fixed-width positions, edge cases)
- Data transformer (aggregation by CUIT/entity, MAX situation, SUM loans)
- Event handlers (upsert idempotency, completion sentinel, exactly-once notification)
- SQS integration (publish/consume round-trip against LocalStack)
- API controllers (CRUD, pagination, validation, 404 handling)

## Project Structure

```
wayni-challenge/
├── services/
│   ├── importer/                  # Write-side service
│   │   ├── app/
│   │   │   ├── Domain/            # Entities, Value Objects, Events
│   │   │   ├── Application/       # Use Cases, Jobs, DTOs, Ports, Notification
│   │   │   ├── Infrastructure/    # Eloquent, SQS, S3, File Parser, Handlers
│   │   │   └── Http/              # Controllers, API Resources
│   │   ├── database/migrations/   # ALL migrations (sole schema owner)
│   │   ├── tests/                 # 175 tests
│   │   ├── Dockerfile
│   │   └── .env
│   └── query/                     # Read-only service
│       ├── app/
│       │   ├── Models/            # Read-only Eloquent models
│       │   └── Http/              # Controllers, API Resources, Requests
│       ├── tests/                 # 18 tests
│       ├── Dockerfile
│       └── .env
├── infrastructure/
│   └── template.yaml              # AWS SAM template
├── docs/
│   └── architecture/              # Detailed architecture docs
├── docker-compose.yml             # 6 services: shared-db, localstack, init, importer, importer-worker, importer-consumer, query
├── init-container.sh              # Bootstrap: migrations + test DB + LocalStack setup
└── README.md
```

## Data Flow

```
1. File arrives (artisan command or HTTP upload)
2. Importer Worker parses TXT line-by-line (streaming, no full-file load)
3. Transformer aggregates in-memory: MAX situation per CUIT, SUM loans per CUIT/entity
4. Events published to SQS: DebtorProcessed, EntityProcessed, ImportCompleted
5. Importer Consumer processes events:
   - Upserts debtor/entity rows (idempotent via processed_events ledger)
   - Increments persisted counter
   - When persisted == expected: fires completion notification (exactly once)
6. Query API reads from the same database (read-only, no consumer needed)
```

## Troubleshooting

```bash
# Check all services
docker compose ps

# View logs
docker compose logs -f importer
docker compose logs -f importer-consumer
docker compose logs -f importer-worker

# Check database
docker compose exec shared-db pg_isready -U wayni -d wayni

# Check LocalStack
curl -s http://localhost:4566/_localstack/health | jq .

# Full reset
docker compose down -v && docker compose up -d
```

## License

MIT
