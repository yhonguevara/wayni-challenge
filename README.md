# Wayni BCRA Deudores Processor

[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4)](https://php.net)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791)](https://postgresql.org)
[![Tests](https://img.shields.io/badge/Tests-141%20passing-brightgreen)]()

Microservices system that processes the BCRA (Central Bank of Argentina) debtor registry file (~6 GB TXT). Parses, transforms, aggregates, and persists data with event-driven CQRS architecture.

## Architecture

```
  WRITE SIDE (importer)                              READ SIDE (query)

  deudores_bcra.txt
  ────────────────►  ┌──────────────┐
  POST /api/upload   │   Importer   │  upload UI + API
  /notify-upload     │  (port 8001) │  dispatches a queue job
  or artisan cmd     └──────┬───────┘
                            │ queue job (ProcessBcraFile)
                     ┌──────▼───────────────────────────────┐
                     │ Importer Worker (queue:work)          │
                     │  ELT pipeline:                        │
                     │  1. stream-parse the TXT (O(1) mem)   │
                     │  2. COPY rows → per-import staging     │
                     │  3. GROUP BY → debtors + entities     │
                     │     (MAX situation by severity, SUM)  │
                     │  4. drop staging                       │
                     │  5. fire completion notification       │
                     └──────┬─────────────────────────────────┘
                            │ writes
                     ┌──────▼───────────┐  reads   ┌──────────────┐
                     │    Shared DB     │◄─────────│  Query API   │  panel UI + REST
                     │   (PostgreSQL)   │          │ (port 8000)  │  read-only
                     └──────────────────┘          └──────────────┘

  Importer is the ONLY writer. Query only reads the same database.
  SQS/webhook/log are used for the completion NOTIFICATION, not for moving data.
```

### Services

| Service | Role | Command |
|---------|------|---------|
| **Importer** | HTTP API + upload UI — receives files, dispatches the processing job | `php artisan serve` |
| **Importer Worker** | Runs the ELT pipeline: stream-parse → COPY to staging → GROUP BY aggregate → notify | `php artisan queue:work` |
| **Query API** | Read-only REST API + query panel UI — queries debtors and entities | `php artisan serve` |
| **Shared DB** | Single PostgreSQL instance — importer writes, query reads | — |
| **LocalStack** | Simulates AWS S3 (file backup) and SQS (notification driver) | — |

### Key Design Decisions

- **ELT, not row-by-row** — the parser streams the file and bulk-loads raw rows into a per-import staging table via Postgres `COPY`; the database then aggregates (`GROUP BY`) into `debtors`/`entities` in one pass. This is what makes the 5.6 GB / 34 M-line file processable (~22 min) with flat memory — a per-row insert path was orders of magnitude too slow.
- **Severity-correct aggregation** — `MAX(situation)` is computed by BCRA risk severity (`01<11<21<23<03<04<05`), not alphabetically, via a rank CASE + array lookup. Plain `MAX`/`GREATEST` on the code string would be wrong (e.g. `05` Irrecuperable must beat `23`).
- **Shared database** — one PostgreSQL instance; importer is the sole writer, query is read-only.
- **Streaming everywhere** — the parser, the S3 download (8 MiB chunks), and the COPY load are all streamed, so memory stays flat regardless of file size.
- **Latest file wins** — aggregation truncates `debtors`/`entities` first, so re-importing replaces (does not accumulate) per RN-03.
- **S3 as backup** — pre-signed uploads land the original file in S3; the worker streams it down to process and leaves the S3 object in place as a backup.

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

> **⚠️ IMPORTANT:** The real BCRA file is ~6 GB. Because of its size, **the original file should be processed with Option A** (copy into the container + artisan command) — the file never passes through an HTTP request. Option B (local path via API) and Option C (direct-to-S3 pre-signed) are convenient for smaller files and client integrations.

> **Quickest way to verify the pipeline end-to-end:** drop a small TXT (a few
> thousand lines is plenty) into the importer container and run Option A — then
> open the query panel at http://localhost:8000/panel to see the results.

### Option A: Artisan command (recommended, REQUIRED for the 6 GB file)

```bash
# Copy the file into the importer container
docker compose cp deudores_bcra.txt importer:/app/storage/app/uploads/

# Process it (streamed line-by-line, never fully loaded in memory)
docker compose exec importer php artisan bcra:process /app/storage/app/uploads/deudores_bcra.txt
```

The command prints a summary: total lines, debtors, entities, and duration.

### Option B: Local path via API

The file must already be reachable from inside the importer container (e.g. copied as in Option A). The endpoint accepts a JSON `path` and queues the job — it does **not** accept a multipart file upload.

```bash
curl -s -X POST http://localhost:8001/api/upload \
  -H "Content-Type: application/json" \
  -d '{"path": "/app/storage/app/uploads/deudores_sample.txt"}' | jq .
# → 202 { "import_log_id": "...", "status": "queued", ... }
```

### Option C: Direct-to-S3 pre-signed upload (for browser/client uploads)

```bash
# 1. Ask for a pre-signed POST. Returns { "upload_url": "...", "fields": { ... } }
curl -s -X POST http://localhost:8001/api/presign \
  -H "Content-Type: application/json" \
  -d '{"filename": "deudores.txt"}' | jq .

# 2. Upload straight to S3 using EVERY field from the response, then the file last.
#    (The browser UI at /upload does this automatically; by hand you must pass
#     each returned form field with -F, e.g. -F "key=..." -F "Policy=..." etc.)
curl -X POST "<upload_url>" \
  -F "key=<fields.key>" \
  -F "<each remaining field from fields>" \
  -F "file=@deudores.txt"

# 3. Notify completion with the same key — this queues processing.
curl -s -X POST http://localhost:8001/api/notify-upload \
  -H "Content-Type: application/json" \
  -d '{"key": "<fields.key>"}' | jq .
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

| Method | Path | Body | Description |
|--------|------|------|-------------|
| `POST` | `/api/upload` | `{ "path": "<container path>" }` | Queue processing of a file already inside the container |
| `POST` | `/api/presign` | `{ "filename": "<name>" }` | Get an S3 pre-signed POST (`upload_url` + `fields`) |
| `POST` | `/api/notify-upload` | `{ "key": "<s3 key>" }` | Notify that an S3 upload completed; queues processing |

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
# Importer: 125 tests (unit + feature + integration)
docker compose exec importer composer test

# Query API: 16 tests
docker compose exec query composer test
```

**Total: 141 tests, 0 failures.**

> **Why `composer test` and not `php artisan test`?** Both services share one
> database host, with `wayni` for runtime and `wayni_test` for tests. The
> `composer test` script forces `DB_DATABASE=wayni_test`, so tests never touch
> your real data. As an extra safety net, the test base class aborts if the
> connected database name does not end in `_test`. Running `php artisan test`
> directly will fail fast with a clear message instead of wiping `wayni`.

Tests cover:
- Domain value objects and business rules (situation severity, amount parsing, CUIT validation)
- File parser (ISO-8859-1 encoding, fixed-width positions, edge cases)
- StagingLoader (chunked COPY load, checkpoint/resume, orphan GC) against a real DB
- Aggregator (severity-correct `MAX(situation)` incl. cross-group cases like `05` beating `23`, `SUM(loans)`, truncate-first) against a real DB
- ImportOrchestrator ELT flow (load → aggregate → drop staging → notify, status transitions, failure path)
- Notification drivers (log / webhook / SQS) and the completion payload
- API controllers (pagination, validation, 404 handling)

## Project Structure

```
wayni-challenge/
├── services/
│   ├── importer/                  # Write-side service (sole schema owner)
│   │   ├── app/
│   │   │   ├── Domain/            # Value Objects (Cuit, Situation, Amount), events
│   │   │   ├── Application/       # Orchestrator, Jobs, Parser, Ports, Notification
│   │   │   ├── Infrastructure/    # StagingLoader, Aggregator, S3 storage, notifications
│   │   │   └── Http/              # Controllers (upload/presign), API Resources
│   │   ├── database/migrations/   # ALL migrations (sole schema owner)
│   │   ├── resources/views/       # Upload UI
│   │   ├── tests/                 # 125 tests
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
├── docker-compose.yml             # 6 services: shared-db, localstack, init, importer, importer-worker, query
├── init-container.sh              # Bootstrap: migrations + test DB + LocalStack setup
└── README.md
```

## Data Flow

```
1. File arrives (artisan command, local-path API, or pre-signed S3 upload)
2. ProcessBcraFile job runs the ELT pipeline in the importer worker:
   a. Resolve the source — local path, or stream-download the S3 object to a temp file
   b. Stream-parse the TXT line by line (171-char fixed width; O(1) memory)
   c. COPY the valid rows into a per-import UNLOGGED staging table, in 5000-row
      chunks, checkpointing last_loaded_line (crash-safe resume)
   d. Aggregate with ONE GROUP BY per target table:
      - debtors:  MAX(situation) by severity rank + SUM(loans), keyed by CUIT
      - entities: SUM(loans), keyed by entity code
      (debtors/entities are truncated first — "latest file wins")
   e. Drop the staging table
   f. Fire the completion notification (log / webhook / SQS driver)
3. Query API reads debtors/entities from the same database (read-only)
```

Memory stays flat throughout (parse, S3 download, and COPY are all streamed); the
database does the heavy aggregation in a single pass.

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
