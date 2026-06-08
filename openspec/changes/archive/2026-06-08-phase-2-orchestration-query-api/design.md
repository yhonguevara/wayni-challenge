# Design: Phase 2 — Orchestration & Query API

## Technical Approach

Wire Phase 1's isolated components (parser, transformer, event publisher) into a coordinated pipeline via `ImportOrchestrator`, exposed through `POST /upload`. On the read side, SQS-driven event handlers upsert into the query database, and REST controllers expose the data. The orchestrator is a thin Application-layer coordinator — it does not contain business logic, only sequencing.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| Orchestrator location | Application vs UseCases | UseCases dir exists but is empty; Orchestrator is a coordinator, not a single use case | **`Application/Orchestrator/`** — matches proposal, semantically clear |
| Job queue driver | `database` vs `sqs` vs `sync` | database enables import_log coupling; SQS adds LocalStack dependency for jobs | **`database`** — ProcessBcraFile tracked via import_logs table, no extra infra |
| File storage | `storage/app/uploads/` vs S3 direct | Local simpler for dev; S3 for prod via FILE_SOURCE env | **Dual-mode** — `Storage::disk('local')` for dev, `Storage::disk('s3')` when `FILE_SOURCE=s3` |
| Notification pattern | Strategy vs Factory | Strategy needs runtime swapping; Factory is simpler for env-driven config | **Static Factory** — `NotificationFactory::fromDriver()` returns single sender |
| Pagination style | Cursor vs Offset | Cursor better for real-time; Offset simpler, matches `per_page`/`page` convention | **Offset** — Laravel's `paginate()` default, adequate for read-mostly data |
| Event handler dispatch | Laravel Queues (SQS driver) vs raw SQS poll | Laravel Queues auto-deserializes, retries; raw poll is more control | **Laravel Queues with SQS connection** — idiomatic, built-in retry/backoff |
| Handler idempotency | `updateOrCreate` vs raw upsert SQL | `updateOrCreate` is Eloquent-idiomatic; raw SQL faster but less readable | **`updateOrCreate`** — matches RN-03 idempotency requirement, safe for reprocessing |
| API response format | JSON:API wrapper vs flat | API contracts spec uses `{data: {...}}` wrapper | **`{data: ...}` wrapper** via API Resources — matches existing contracts |

## Data Flow

```
POST /upload (UploadController)
    │  Validate file (txt, ≤6GB, MIME)
    │  Store to storage/app/uploads/{timestamp}_{name}
    │  Create ImportLog (status=pending)
    │  Dispatch ProcessBcraFile job
    ▼
ProcessBcraFile Job (async, database queue)
    │  Resolve ImportOrchestrator via DI
    ▼
ImportOrchestrator::orchestrate(filePath, importLogId)
    │
    ├─→ BcraFileParser(filePath).parse()        → LazyCollection<BcraRecordDTO>
    ├─→ BcraDataTransformer(records).transform() → {debtors, entities}
    ├─→ Build DomainEvent[] from aggregates
    ├─→ EventPublisher.publishBatch(events)       → SQS (batch of 10)
    ├─→ EventPublisher.publishImportCompleted()   → SQS import-completed queue
    ├─→ NotificationSender.send(ImportCompleted)  → log/webhook/sqs
    └─→ Update ImportLog (status=done, stats)
    
    On failure: Update ImportLog (status=failed, error_message), rethrow

SQS Queues (LocalStack)
    │
    ├─→ debtor-events    → UpsertDebtorHandler    → Debtor::updateOrCreate
    ├─→ entity-events    → UpsertEntityHandler     → Entity::updateOrCreate
    └─→ import-completed → LogImportCompletionHandler → Log::info

Query API
    GET /debtors/{cuit}      → DebtorController@show
    GET /debtors/top/{n}     → DebtorController@top
    GET /debtors?situation=X → DebtorController@index
    GET /entities/{code}     → EntityController@show
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `services/importer/app/Application/Orchestrator/ImportOrchestrator.php` | Create | Coordinates parse→transform→publish→notify→update log |
| `services/importer/app/Application/Jobs/ProcessBcraFile.php` | Create | Queued job wrapping orchestrator call, 3 retries |
| `services/importer/app/Application/Notification/NotificationSender.php` | Create | Interface: `send(ImportCompleted): void` |
| `services/importer/app/Application/Notification/LogNotification.php` | Create | Log::info implementation |
| `services/importer/app/Application/Notification/WebhookNotification.php` | Create | HTTP POST with retry |
| `services/importer/app/Application/Notification/SqsNotification.php` | Create | Publish to import-completed queue |
| `services/importer/app/Application/Notification/NotificationFactory.php` | Create | Static factory from NOTIFICATION_DRIVER env |
| `services/importer/app/Http/Controllers/UploadController.php` | Create | POST /upload handler |
| `services/importer/app/Http/Requests/UploadFileRequest.php` | Create | File validation rules |
| `services/importer/routes/api.php` | Modify | Add POST /upload route |
| `services/importer/app/Providers/AppServiceProvider.php` | Modify | Bind EventPublisher, NotificationSender |
| `services/importer/config/queue.php` | Modify | Add database queue connection |
| `services/query/app/Application/Handlers/UpsertDebtorHandler.php` | Create | Consumes DebtorProcessed, upserts debtor |
| `services/query/app/Application/Handlers/UpsertEntityHandler.php` | Create | Consumes EntityProcessed, upserts entity |
| `services/query/app/Application/Handlers/LogImportCompletionHandler.php` | Create | Consumes ImportCompleted, logs |
| `services/query/app/Application/DTOs/DebtorProcessedEvent.php` | Create | Event DTO for deserialization |
| `services/query/app/Application/DTOs/EntityProcessedEvent.php` | Create | Event DTO for deserialization |
| `services/query/app/Application/DTOs/ImportCompletedEvent.php` | Create | Event DTO for deserialization |
| `services/query/app/Http/Controllers/DebtorController.php` | Create | show, top, index actions |
| `services/query/app/Http/Controllers/EntityController.php` | Create | show action |
| `services/query/app/Http/Resources/DebtorResource.php` | Create | JSON:API debtor representation |
| `services/query/app/Http/Resources/EntityResource.php` | Create | JSON:API entity representation |
| `services/query/app/Http/Requests/ShowDebtorRequest.php` | Create | CUIT validation |
| `services/query/app/Http/Requests/TopDebtorsRequest.php` | Create | N param validation |
| `services/query/app/Http/Requests/IndexDebtorsRequest.php` | Create | Filter + pagination validation |
| `services/query/app/Http/Requests/ShowEntityRequest.php` | Create | Entity code validation |
| `services/query/routes/api.php` | Create | Query endpoint routes |
| `services/query/config/queue.php` | Modify | SQS driver with LocalStack endpoint |
| `services/query/app/Providers/AppServiceProvider.php` | Modify | Register event-handler mapping |

## Interfaces / Contracts

### ImportOrchestrator

```php
final class ImportOrchestrator
{
    public function __construct(
        private readonly EventPublisher $eventPublisher,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function orchestrate(string $filePath, int $importLogId): ImportLog;
}
```

### NotificationSender

```php
interface NotificationSender
{
    public function send(ImportCompleted $event): void;
}
```

### Event Handler Pattern (Query Service)

```php
// Each handler implements Laravel's ShouldQueue
final class UpsertDebtorHandler implements ShouldQueue
{
    public string $queue = 'debtor-events';
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(DebtorProcessedEvent $event): void
    {
        Debtor::updateOrCreate(
            ['identification_number' => $event->identificationNumber],
            ['max_situation' => $event->maxSituation, 'total_loan_amount' => $event->totalLoans],
        );
    }
}
```

### Queue Config (Query Service)

```php
'sqs' => [
    'driver' => 'sqs',
    'key' => env('AWS_ACCESS_KEY_ID', 'test'),
    'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
    'prefix' => env('SQS_PREFIX', 'http://localhost:4566/000000000000'),
    'queue' => 'debtor-events',
    'suffix' => '',
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'endpoint' => env('AWS_ENDPOINT_URL', 'http://localhost:4566'),
],
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | ImportOrchestrator: mock parser, transformer, publisher, notifier — verify call sequence and ImportLog updates | PHPUnit mocks, assert method invocation order |
| Unit | NotificationSender implementations: LogNotification writes log, WebhookNotification retries on failure | Mock Log and Http facades |
| Feature | UploadController: valid file → 202 + import_log_id; invalid MIME → 422; oversized → 422 | `$this->postJson('/upload', ...)` with UploadedFile fake |
| Feature | DebtorController: 200 with data, 404 for unknown CUIT, 422 for bad format | Seed debtors table, assert JSON structure |
| Feature | EntityController: 200 with data, 404, 422 for bad code | Seed entities table |
| Feature | Pagination: `?per_page=10&page=2` returns correct slice | Seed 25 debtors, assert meta |
| Integration | Event handlers: dispatch DebtorProcessedEvent → verify Debtor row created | `dispatch_sync()` with real DB, assert upsert |

## Migration / Rollout

No schema migration in this phase — tables already exist from Phase 1. New code is purely additive. Rollback: remove routes and redeploy. No data loss risk.

## Open Questions

- [ ] Should `ProcessBcraFile` job store the file path or the S3 key? (Currently: local path — needs adaptation for S3 mode in Phase 3)
- [ ] Confirm `NOTIFICATION_DRIVER` default: `log` for all environments, or `sqs` for production?
