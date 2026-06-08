# Proposal: Phase 2 — Orchestration & Query API

## Intent

Close the loop from file upload to queryable data. Phase 1 delivered parsing, transformation, and events — but there's no orchestrator to coordinate them, no upload endpoint, no event consumers, and no API to query results. Phase 2 bridges the write side (Importer) and read side (Query API).

## Scope

### In Scope
- **ImportOrchestrator**: coordinates parse → transform → publish events → notify, tracking stats in `import_logs`
- **ProcessBcraFile Job**: async Laravel Job wrapping the orchestrator
- **UploadController** (`POST /upload`): validates file, saves to storage, dispatches Job, returns `import_log_id`
- **Notification system**: `LogNotification` (always), `WebhookNotification`, `SqsNotification` — factory-driven via `NOTIFICATION_DRIVER`
- **Event Handlers** (Query API): `UpsertDebtorHandler`, `UpsertEntityHandler`, `LogImportCompletionHandler` consuming SQS via Laravel Queues
- **Query endpoints**: `GET /debtors/{cuit}`, `GET /debtors/top/{n}`, `GET /debtors?situation=X`, `GET /entities/{code}`
- **API Resources + Form Requests**: `DebtorResource`, `EntityResource`, validation requests

### Out of Scope
- ECS task dispatch (deferred to Phase 3)
- Auth, API versioning, rate limiting
- Frontend progress tracking
- Advanced retry/DLQ strategies
- Monitoring/metrics

## Capabilities

### New Capabilities
- `import-orchestration`: ImportOrchestrator + ProcessBcraFile Job + UploadController — the pipeline coordinator that wires parsing, transformation, event publishing, and notification
- `query-api-endpoints`: REST API on Query service — debtors by CUIT, top N, filtered list, entities by code, pagination
- `event-handlers`: SQS consumers that handle `DebtorProcessed`, `EntityProcessed`, `ImportCompleted` — upsert read models, log completions
- `notification-system`: Multi-channel completion notification — structured JSON log (always), optional webhook and SQS

### Modified Capabilities
- `file-ingestion`: Add `POST /upload` requirement — validates TXT file, persists, dispatches async processing, returns tracking ID

## Approach

**Importer**: ImportOrchestrator receives file path → `BcraFileParser` (streaming) → `BcraDataTransformer` → `SqsEventPublisher.publishBatch()` → `NotificationSender.send()` → update `ImportLog`. Wrapped in a `ProcessBcraFile` Job dispatched by `UploadController@store`.

**Query API**: Laravel Queue worker (SQS driver) processes events. Handlers use `upsert()` for idempotency (RN-03). `DebtorController` and `EntityController` query Eloquent models with Form Request validation. Pagination via `per_page` query param.

**Notification**: `NotificationFactory` instantiates `LogNotification` (default) + optional `WebhookNotification`/`SqsNotification` based on `NOTIFICATION_DRIVER`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `services/importer/app/Application/Orchestrator/` | New | ImportOrchestrator |
| `services/importer/app/Application/Jobs/` | New | ProcessBcraFile |
| `services/importer/app/Application/Notification/` | New | Interface + implementations + factory |
| `services/importer/app/Http/Controllers/UploadController.php` | New | File upload endpoint |
| `services/importer/routes/api.php` | Modified | Add POST /upload |
| `services/query/app/Application/Handlers/` | New | 3 event handlers |
| `services/query/app/Http/Controllers/` | New | DebtorController, EntityController |
| `services/query/app/Http/Resources/` | New | DebtorResource, EntityResource |
| `services/query/app/Http/Requests/` | New | 4 Form Requests |
| `services/query/routes/api.php` | New | Query endpoints |
| `services/query/config/queue.php` | Modified | SQS driver config |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| SQS handler ordering (debtors arrive before entities) | Low | Upsert is idempotent; order irrelevant |
| Large batch publish failure (partial SQS batch) | Med | SqsEventPublisher already handles `Failed[]` per batch |
| File validation bypass | Low | MIME check + extension whitelist + size limit |

## Rollback Plan

Revert commits, redeploy. No DB schema changes in this phase — only new code. If `POST /upload` endpoint misbehaves, remove route and redeploy importer. Query endpoints are additive — remove routes to disable.

## Dependencies

- Phase 1 completed (parser, transformer, events, SQS publisher, migrations, models, LocalStack setup)
- LocalStack running with SQS queues created (`localstack:setup`)

## Success Criteria

- [ ] `POST /upload` accepts a TXT file and returns `import_log_id` (202)
- [ ] File processing completes end-to-end: parse → transform → publish → notify → `import_logs.status = 'done'`
- [ ] Query API returns correct debtor/entity data consumed from SQS events
- [ ] All endpoints validate inputs (422 on bad CUIT, out-of-range n, invalid situation)
- [ ] All endpoints return 404 for unknown records
- [ ] All feature tests pass (upload + handlers + endpoints)
- [ ] Notification fires on import completion (log visible in stdout)
