# System Overview

## Description

The system processes the BCRA (Central Bank of Argentina) debtor registry file (fixed-position TXT format), transforms data according to defined business rules, persists it to the database, and exposes a query API. Upon completion, it emits a notification.

## Actors

| Actor | Role |
|-------|------|
| Operator | Uploads the TXT file via endpoint or CLI |
| External System | Queries debtors/entities via REST API |
| Notification | Receives process completion event (webhook/log/SQS) |

## High-Level Flow (Event-Driven)

```
[TXT File]
    → [POST /upload] (Importer Service)
    → [Parse & Transform]
    → [Publish: DeudorProcessed, EntityProcessed events to SQS]
    → [Upload to S3 (optional)]
    → [Publish: ImportCompleted event to SQS]
    → [Log structured completion]

[SQS Queue: deudor-events]
    → [Query API Worker consumes events]
    → [Upsert to Query DB (read model)]

[Client] → [GET /deudores/{cuit}]   → [Query API] → [Query DB]
[Client] → [GET /entidades/{codigo}] → [Query API] → [Query DB]
[Client] → [GET /deudores/top/{n}]  → [Query API] → [Query DB]
```

---

## Architecture Decision Records

### ADR-01: Real Microservices with Database-per-Service and CQRS

**Decision:** Two independent Laravel services:
- **Importer Service** (Write Side): processes the file, publishes events to SQS
- **Query API** (Read Side): consumes events from SQS, maintains read model optimized for queries

Each service has its **own PostgreSQL database**. Communication is **asynchronous via SQS** (event-driven).

**Justification:**
- Database-per-service avoids coupling and allows independent scaling
- CQRS separates responsibilities: write side optimized for batch processing, read side optimized for queries
- SQS guarantees at-least-once delivery and decouples services
- Bonus: real asynchronous processing (not just simulated)

**Discarded alternative:** Services sharing the same DB. Discarded because it violates the microservices principle (each service must own its data).

### ADR-02: PostgreSQL as Database

**Decision:** PostgreSQL 18 for both services.

**Justification:**
- Native `ON CONFLICT DO UPDATE` support (atomic upsert)
- Precise numeric types (`NUMERIC`) for financial amounts
- Better index support for ranking queries
- JSONB for flexible metadata if needed

### ADR-03: Chunk Processing with Laravel Lazy Collections

**Decision:** The TXT file is read with `LazyCollection` in chunks of 1000 lines.

**Justification:**
- The BCRA registry can contain millions of records (6GB file)
- Loading the entire file into memory would cause OOM
- `LazyCollection` + batch `upsert()` is the idiomatic Laravel solution
- Enables streaming processing without loading everything into memory

### ADR-04: Event-Driven with SQS for Inter-Service Communication

**Decision:**
- Importer publishes domain events to SQS: `DeudorProcessed`, `EntityProcessed`, `ImportCompleted`
- Query API consumes those events with Laravel Queues (SQS driver)
- SQS guarantees at-least-once delivery and allows automatic retries

**Justification:**
- Completely decouples services
- Allows scaling the consumer independently
- SQS is explicitly listed as an allowed tool
- Implements the bonus of "SQS usage for asynchronous processing"
- Resilience patterns: dead-letter queue for failed messages

### ADR-05: Multi-Channel Notification (log + webhook + SQS)

**Decision:**
- **Always:** structured JSON log in stdout with process metrics
- **Optional (configurable):** webhook POST to external URL
- **Optional (configurable):** SQS message to notifications queue

**Justification:**
- Log is the most reliable common denominator
- Webhook and SQS are bonuses that demonstrate versatility
- Configuration via `.env` allows enabling/disabling without code changes

### ADR-06: LocalStack for S3 and SQS

**Decision:** Implement S3 and SQS using the official AWS driver in Laravel (`aws/aws-sdk-php`) pointing to the LocalStack endpoint.

**Justification:**
- Allows validating the real flow without cost
- LocalStack is explicitly listed as an allowed tool
- Facilitates integration testing with real AWS services

### ADR-07: Clean Architecture with DDD Tactical Patterns

**Decision:**
- **Domain Layer:** Entities, Value Objects, Domain Events, Repositories (interfaces)
- **Application Layer:** Use Cases, DTOs, Event Handlers
- **Infrastructure Layer:** Concrete implementations (Eloquent, SQS, S3, File Parser)
- **Presentation Layer:** Controllers, API Resources, Form Requests

**Justification:**
- Clearly separates responsibilities
- Facilitates testing (inverted dependencies)
- Demonstrates advanced architecture knowledge
