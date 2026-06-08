# Event Handlers Specification

## Purpose

SQS event consumers on the Query API service that maintain the read model via idempotent upsert operations.

## Requirements

### REQ-HND-001: UpsertDebtorHandler

The system MUST consume `DebtorProcessed` events and upsert debtor records in the query database.

#### Scenario: New debtor inserted

- GIVEN a `DebtorProcessed` event with a new `identificationNumber`
- WHEN the handler processes the event
- THEN a new row MUST be inserted into the `debtors` table

#### Scenario: Existing debtor updated

- GIVEN a `DebtorProcessed` event for an existing `identificationNumber`
- WHEN the handler processes the event
- THEN the existing row MUST be updated (upsert on `identification_number` unique constraint)
- AND no duplicate rows MUST be created (RN-03)

### REQ-HND-002: UpsertEntityHandler

The system MUST consume `EntityProcessed` events and upsert entity records in the query database.

#### Scenario: New entity inserted

- GIVEN an `EntityProcessed` event with a new `entityCode`
- WHEN the handler processes the event
- THEN a new row MUST be inserted into the `entities` table

#### Scenario: Existing entity updated

- GIVEN an `EntityProcessed` event for an existing `entityCode`
- WHEN the handler processes the event
- THEN the existing row MUST be updated (upsert on `entity_code` unique constraint)

### REQ-HND-003: LogImportCompletionHandler

The system MUST consume `ImportCompleted` events and log a summary.

#### Scenario: Completion logged

- GIVEN an `ImportCompleted` event is received
- WHEN the handler processes the event
- THEN it MUST log filename, totals, and duration at `info` level

### REQ-HND-004: Queue Configuration

The system MUST configure Laravel Queues with SQS driver and dedicated queue names.

#### Scenario: Queue routing

- GIVEN the query worker is running
- WHEN events arrive on SQS
- THEN `DebtorProcessed` MUST be routed to `debtor-events` queue
- AND `EntityProcessed` MUST be routed to `entity-events` queue
- AND `ImportCompleted` MUST be routed to `import-completed` queue

#### Scenario: Idempotent reprocessing

- GIVEN a message is delivered more than once (SQS at-least-once)
- WHEN the handler processes the duplicate
- THEN the upsert operation MUST produce the same result as the first processing
