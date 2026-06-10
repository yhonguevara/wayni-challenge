# Wayni BCRA Deudores Processor

[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4)](https://php.net)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791)](https://postgresql.org)
[![Tests](https://img.shields.io/badge/Tests-189%20passing-brightgreen)]()

Microservices system that processes the BCRA (Central Bank of Argentina) debtor registry file (~6 GB TXT). Parses, transforms, aggregates, and persists data with event-driven CQRS architecture.

## Architecture

```
  WRITE SIDE (importer)                              READ SIDE (query)

  deudores_bcra.txt
  ────────────────►  ┌──────────────┐
  POST /upload       │   Importer   │  upload UI + API
  or artisan cmd     │  (port 8001) │  dispatches a queue job
                     └──────┬───────┘
                            │ queue job
                     ┌──────▼─────────┐
                     │ Importer Worker│  parses the TXT, aggregates,
                     │  (queue:work)  │  publishes domain events
                     └──────┬─────────┘
                            │ SQS (3 queues, via LocalStack)
                     ┌──────▼───────────┐
                     │ Importer Consumer│  upserts rows, counts,
                     │ (events:consume) │  notifies once when done
                     └──────┬───────────┘
                            │ writes
                     ┌──────▼───────────┐  reads   ┌──────────────┐
                     │    Shared DB     │◄─────────│  Query API   │  panel UI + REST
                     │   (PostgreSQL)   │          │ (port 8000)  │  read-only
                     └──────────────────┘          └──────────────┘

  Importer is the ONLY writer. Query only reads the same database.
```

### Services

| Service | Role | Command |
|---------|------|---------|
| **Importer** | HTTP API + upload UI — receives files, dispatches processing jobs | `php artisan serve` |
| **Importer Worker** | Processes Laravel queue jobs — parses TXT, publishes events to SQS | `php artisan queue:work` |
| **Importer Consumer** | Consumes SQS events — upserts debtors/entities, fires completion notification | `php artisan events:consume` |
| **Query API** | Read-only REST API + query panel UI — queries debtors and entities | `php artisan serve` |
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

> **Getting a file to process:** Data files are not committed to the repo
> (the `.gitignore` excludes `deudores*.txt`). Use the official BCRA file
> provided with the challenge, or any TXT that follows the BCRA fixed-width
> format (171 chars/line). Place it at the project root before running the
> commands below.

> **⚠️ IMPORTANT:** The real BCRA file is ~6 GB. Due to performance and local capacity constraints, **the original file can only be processed using Option B** (copy to container + artisan command). Options A and C are provided for testing with smaller files or API integration purposes.

> **Quickest way to verify the pipeline end-to-end:** drop a small TXT (a few
> thousand lines is plenty) at the project root and run Option B with it — then
> open the query panel at http://localhost:8000/panel to see the results.

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

Each service serves the front-end for its own responsibility:

| URL | Served by | Description |
|-----|-----------|-------------|
| http://localhost:8001/ | Importer | Redirects to the upload page |
| http://localhost:8001/upload | Importer | File upload interface (Mode A: S3 pre-signed, Mode B: local path) |
| http://localhost:8000/ | Query | Redirects to the query panel |
| http://localhost:8000/panel | Query | API testing panel — try all Query API endpoints interactively |

Both pages link to each other, so you can navigate between upload and query without typing URLs.

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
| `GET` | `/api/debtors/{cuit}` | Get debtor by CUIT |
| `GET` | `/api/debtors/top/{n}` | Top N debtors by total loan amount |
| `GET` | `/api/debtors?situation={code}` | List debtors with filters and pagination |
| `GET` | `/api/entities/{code}` | Get entity by code |

### Examples

```bash
# Get debtor by CUIT
curl -s http://localhost:8000/api/debtors/20123456789 | jq .

# Top 10 debtors
curl -s http://localhost:8000/api/debtors/top/10 | jq .

# Filter by situation code
curl -s "http://localhost:8000/api/debtors?situation=05&per_page=50" | jq .

# Get entity
curl -s http://localhost:8000/api/entities/00011 | jq .
```

**Situation codes:** `01` normal, `03` with observation, `04` non-compliant, `05` deficient, `11` doubtful, `21` irrecoverable, `23` irrecoverable (judicial)

## Testing

Run the suites with `composer test` — **not** `php artisan test` directly:

```bash
# Importer: 173 tests (unit + feature + integration)
docker compose exec importer composer test

# Query API: 16 tests
docker compose exec query composer test
```

**Total: 189 tests, 452 assertions, 0 failures.**

> **Why `composer test` and not `php artisan test`?** Both services share one
> database host, with `wayni` for runtime and `wayni_test` for tests. The
> `composer test` script forces `DB_DATABASE=wayni_test`, so tests never touch
> your real data. As an extra safety net, the test base class aborts if the
> connected database name does not end in `_test`. Running `php artisan test`
> directly will fail fast with a clear message instead of wiping `wayni`.

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
│   ├── importer/                  # Write-side service (sole schema owner)
│   │   ├── app/
│   │   │   ├── Domain/            # Entities, Value Objects, Events
│   │   │   ├── Application/       # Use Cases, Jobs, DTOs, Ports, Notification
│   │   │   ├── Infrastructure/    # Eloquent, SQS, S3, File Parser, Handlers
│   │   │   └── Http/              # Controllers, API Resources
│   │   ├── database/migrations/   # ALL migrations (sole schema owner)
│   │   ├── resources/views/       # Upload UI
│   │   ├── tests/                 # 173 tests
│   │   ├── Dockerfile
│   │   └── .env
│   └── query/                     # Read-only service (no migrations)
│       ├── app/
│       │   ├── Models/            # Read-only Eloquent models
│       │   └── Http/              # Controllers, API Resources, Requests
│       ├── resources/views/       # Query panel UI
│       ├── tests/                 # 16 tests (DatabaseTransactions, never migrates)
│       ├── Dockerfile
│       └── .env
├── infrastructure/
│   └── template.yaml              # AWS SAM template
├── docs/
│   └── architecture/              # Detailed architecture docs
├── docker-compose.yml             # 7 services: shared-db, localstack, init, importer, importer-worker, importer-consumer, query
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
