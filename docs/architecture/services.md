# Service Decomposition

## Importer Service (Write Side)

**Single responsibility:** Receive the file, parse it, transform it, publish events, and emit notification.

**Technology:** Laravel 13 · PHP 8.5 · artisan command + HTTP endpoint

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/upload` | Receives TXT file as multipart/form-data |
| `POST` | `/process` | Processes a file from local path (body: `{"path": "/storage/..."}`) |

### Jobs / Commands

| Name | Type | Description |
|------|------|-------------|
| `ProcessBcraFile` | Job | Asynchronous file processing |
| `PublishDebtorEvents` | Job | Publishes debtor events to SQS |
| `PublishEntityEvents` | Job | Publishes entity events to SQS |
| `bcra:process {path}` | Artisan Command | Triggers processing from CLI |

### Domain Events Published

| Event | Payload | Description |
|-------|---------|-------------|
| `DebtorProcessed` | `{identificationNumber, maxSituation, totalLoanAmount}` | A debtor was processed |
| `EntityProcessed` | `{entityCode, totalLoanAmount}` | An entity was processed |
| `ImportCompleted` | `{filename, totalLines, totalDebtors, totalEntities, durationMs}` | Process completed |

---

## Query Service (Read Side)

**Single responsibility:** Consume SQS events, maintain read model, and respond to queries.

**Technology:** Laravel 13 · PHP 8.5 · API Resources · Laravel Queues (SQS)

### Event Handlers

| Handler | Event | Action |
|---------|-------|--------|
| `UpsertDebtorHandler` | `DebtorProcessed` | Upsert debtor in query DB |
| `UpsertEntityHandler` | `EntityProcessed` | Upsert entity in query DB |
| `LogImportCompletionHandler` | `ImportCompleted` | Log completion |

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/debtors/{cuit}` | Query debtor by CUIT/CUIL |
| `GET` | `/entities/{code}` | Query entity by code |
| `GET` | `/debtors/top/{n}` | Top N debtors by loan sum |
| `GET` | `/debtors` | List debtors with optional filters (`?situation=X`) |

---

## Infrastructure Services

| Service | Image | Port |
|---------|-------|------|
| `importer-db` | `postgres:18-alpine` | 5432 |
| `query-db` | `postgres:18-alpine` | 5433 |
| `localstack` | `localstack/localstack:4.14` | 4566 |
| `importer` | `./importer-service` (custom) | 8001 |
| `query-service` | `./query-service` (custom) | 8000 |
| `query-worker` | `./query-service` (custom) | N/A (queue worker) |

---

*Last updated: Phase 0 SDD*
