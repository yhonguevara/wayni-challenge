# Import Orchestration Specification

## Purpose

Coordinate the BCRA file processing pipeline: parse → transform → publish events → notify, with async job dispatch and import tracking.

## Requirements

### REQ-ORC-001: ImportOrchestrator Pipeline

The system MUST provide an `ImportOrchestrator` that coordinates the full processing pipeline and returns an `ImportLog` with stats.

#### Scenario: Successful pipeline

- GIVEN a valid BCRA file path is provided
- WHEN the orchestrator executes the pipeline
- THEN it MUST run Parser → Transformer → EventPublisher → NotificationSender in sequence
- AND return an `ImportLog` with `total_records`, `valid_records`, `invalid_records`, and `duration`

#### Scenario: Partial failure tracking

- GIVEN some records fail validation during parsing
- WHEN the orchestrator completes
- THEN `invalid_records` MUST reflect the count of skipped records
- AND `valid_records` MUST reflect successfully processed records

### REQ-ORC-002: ProcessBcraFile Job

The system MUST wrap the orchestrator in a `ProcessBcraFile` Laravel Job for async execution.

#### Scenario: Job dispatch and execution

- GIVEN a `ProcessBcraFile` job is dispatched with a file path and import_log_id
- WHEN the queue worker processes the job
- THEN it MUST invoke the `ImportOrchestrator` and update the `ImportLog` status

#### Scenario: Retry on failure

- GIVEN the job fails due to a transient error
- WHEN the job is retried
- THEN it MUST retry up to 3 times maximum
- AND set `ImportLog.status` to `failed` after exhausting retries

### REQ-ORC-003: UploadController

The system MUST expose `POST /upload` accepting a TXT file via multipart/form-data.

#### Scenario: Valid file upload

- GIVEN a valid `.txt` file with `text/plain` MIME type under 6GB
- WHEN `POST /upload` is called
- THEN the system MUST save the file to storage, create an `ImportLog` with status `pending`, dispatch `ProcessBcraFile`, and return `202` with `import_log_id`

#### Scenario: Invalid file rejected

- GIVEN a file that is not `.txt`, not `text/plain`, or exceeds 6GB
- WHEN `POST /upload` is called
- THEN the system MUST return `422` with validation errors

### REQ-ORC-004: Import Log Tracking

The system MUST track import progress in the `import_logs` table.

#### Scenario: Status transitions

- GIVEN an import is created
- WHEN processing progresses
- THEN status MUST transition: `pending` → `processing` → `completed` or `failed`
- AND `started_at`, `completed_at`, and count fields MUST be updated accordingly

#### Scenario: Import log queryable

- GIVEN an `import_log_id` is returned from upload
- WHEN the import log is queried
- THEN it MUST return current status, record counts, and timing data
