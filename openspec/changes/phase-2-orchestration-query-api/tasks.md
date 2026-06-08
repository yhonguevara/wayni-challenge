# Tasks: Phase 2 — Orchestration & Query API

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~2,100 (prod: ~1,200, tests: ~900) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Importer Core) → PR 2 (Query Core) |
| Delivery strategy | single-pr |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Scope | Lines |
|------|------|-------|-------|
| 1 | Importer: Notifications + Orchestrator + Upload | Notification system, ImportOrchestrator, ProcessBcraFile, UploadController, routes | ~650 |
| 2 | Query: Queue + Handlers + API | Queue config, event DTOs, handlers, resources, requests, controllers, routes | ~900 |
| 3 | All tests | Unit, feature, integration for both services | ~550 |

## Phase 1: Foundation — Interfaces & Config

- [x] 1.1 Create `NotificationSender` interface in `importer/app/Application/Notification/`
- [x] 1.2 Configure SQS connection in `query/config/queue.php` with LocalStack endpoint + 3 queues
- [x] 1.3 Create `DebtorProcessedEvent`, `EntityProcessedEvent`, `ImportCompletedEvent` DTOs in `query/app/Application/DTOs/`

## Phase 2: Core — Notification Implementations (Importer)

- [x] 2.1 Create `LogNotification` — `Log::info` with structured JSON output
- [x] 2.2 Create `WebhookNotification` — HTTP POST via `Http` facade, throw on missing URL
- [x] 2.3 Create `SqsNotification` — publish to notifications queue
- [x] 2.4 Create `NotificationFactory` — static `fromDriver()` resolving `log|webhook|sqs`

## Phase 3: Core — ImportOrchestrator & Job (Importer)

- [x] 3.1 Create `ImportOrchestrator` in `Application/Orchestrator/` — parse → transform → publishBatch → publishImportCompleted → notify → update `ImportLog` with stats
- [x] 3.2 Create `ProcessBcraFile` Job in `Application/Jobs/` — receives `filePath`+`importLogId`, delegates to orchestrator, 3 retries, sets failed status on exhaustion
- [x] 3.3 Bind `EventPublisher` (SqsEventPublisher) + `NotificationSender` (via factory) in `AppServiceProvider`

## Phase 4: Integration — Upload Endpoint (Importer)

- [x] 4.1 Create `UploadFileRequest` — validate `.txt`, `text/plain`, ≤6GB
- [x] 4.2 Create `UploadController@store` — persist file, create `ImportLog(pending)`, dispatch `ProcessBcraFile`, return 202
- [x] 4.3 Replace stub routes in `routes/api.php` with `POST /upload`

## Phase 5: Core — Event Handlers (Query)

- [x] 5.1 Create `UpsertDebtorHandler implements ShouldQueue` — `Debtor::updateOrCreate` on `identification_number`, queue `debtor-events`
- [x] 5.2 Create `UpsertEntityHandler implements ShouldQueue` — `Entity::updateOrCreate` on `entity_code`, queue `entity-events`
- [x] 5.3 Create `LogImportCompletionHandler implements ShouldQueue` — `Log::info` summary, queue `import-completed`
- [x] 5.4 Register handler→queue mapping in Query's `AppServiceProvider`

## Phase 6: API — Resources & Validation (Query)

- [x] 6.1 Create `DebtorResource` — wraps `{data: {identification_number, max_situation, total_loan_amount, ...}}`
- [x] 6.2 Create `EntityResource` — wraps `{data: {entity_code, total_loan_amount, ...}}`
- [x] 6.3 Create `ShowDebtorRequest` — CUIT: 11 numeric digits
- [x] 6.4 Create `TopDebtorsRequest` — n: 1..1000
- [x] 6.5 Create `IndexDebtorsRequest` — situation valid codes, per_page ≤200
- [x] 6.6 Create `ShowEntityRequest` — code ≤5 alphanumeric chars

## Phase 7: Presentation — Controllers & Routes (Query)

- [x] 7.1 Create `DebtorController` — `show(CUIT)`, `top(n)`, `index(?situation, ?per_page, ?page)` with offset pagination, default 50
- [x] 7.2 Create `EntityController` — `show(code)` returning `EntityResource`, 404 on miss
- [x] 7.3 Create `routes/api.php` with 4 GET endpoints

## Phase 8: Testing

- [x] 8.1 Unit test ImportOrchestrator: mock all deps, verify sequence + ImportLog status transitions per REQ-ORC-001
- [x] 8.2 Unit test LogNotification: assert `Log::info` called with filename + totals per REQ-NTF-002
- [x] 8.3 Unit test WebhookNotification: assert HTTP POST; URL missing throws per REQ-NTF-003
- [x] 8.4 Unit test NotificationFactory: all 3 drivers + default + invalid per REQ-NTF-005
- [x] 8.5 Feature test UploadController: valid→202, wrong ext→422, wrong MIME→422, oversized→422, missing→422 per REQ-ING-007
- [x] 8.6 Feature test DebtorController: 200 with data, 404 unknown CUIT, 422 bad format, pagination, situation filter per REQ-API-001/002/003
- [x] 8.7 Feature test EntityController: 200, 404, 422 per REQ-API-004
- [x] 8.8 Integration test UpsertDebtorHandler: dispatch event sync, assert upsert per REQ-HND-001
- [x] 8.9 Integration test UpsertEntityHandler: dispatch event sync, assert upsert per REQ-HND-002
- [x] 8.10 Integration test LogImportCompletionHandler: dispatch event sync, assert log per REQ-HND-003
