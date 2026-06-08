# Domain Events Specification

## Purpose

Define the domain event classes, the EventPublisher port (interface), and the SQS infrastructure implementation for inter-service communication.

## Requirements

### REQ-EVT-001: DebtorProcessed Event

The system MUST define a `DebtorProcessed` domain event carrying the aggregated debtor data.

#### Scenario: Event payload

- GIVEN a debtor has been aggregated
- WHEN a `DebtorProcessed` event is constructed
- THEN it MUST contain: `identificationNumber` (string), `maxSituation` (string), `totalLoans` (float), `processedAt` (DateTimeImmutable)

#### Scenario: Event is immutable

- GIVEN a `DebtorProcessed` instance
- WHEN its properties are accessed
- THEN all properties MUST be `readonly`
- AND no setter methods MUST exist

### REQ-EVT-002: EntityProcessed Event

The system MUST define an `EntityProcessed` domain event carrying the aggregated entity data.

#### Scenario: Event payload

- GIVEN an entity has been aggregated
- WHEN an `EntityProcessed` event is constructed
- THEN it MUST contain: `entityCode` (string), `totalLoans` (float), `processedAt` (DateTimeImmutable)

#### Scenario: Event is immutable

- GIVEN an `EntityProcessed` instance
- WHEN its properties are accessed
- THEN all properties MUST be `readonly`

### REQ-EVT-003: ImportCompleted Event

The system MUST define an `ImportCompleted` domain event signaling the end of file processing.

#### Scenario: Event payload

- GIVEN file processing has completed
- WHEN an `ImportCompleted` event is constructed
- THEN it MUST contain: `filename` (string), `totalDebtors` (int), `totalEntities` (int), `durationMs` (int), `completedAt` (DateTimeImmutable)

### REQ-EVT-004: EventPublisher Port

The Application layer MUST define an `EventPublisher` interface that Infrastructure implements.

#### Scenario: Interface contract

- GIVEN the `EventPublisher` interface is inspected
- WHEN its methods are read
- THEN it MUST declare `publishDebtorProcessed(DebtorProcessed $event): void`
- AND `publishEntityProcessed(EntityProcessed $event): void`
- AND `publishImportCompleted(ImportCompleted $event): void`

#### Scenario: Interface in Application layer

- GIVEN the `EventPublisher` interface location
- WHEN inspected
- THEN it MUST reside in `App\Application\Ports\`
- AND it MUST NOT import any Infrastructure or Laravel classes

### REQ-EVT-005: SqsEventPublisher Implementation

The Infrastructure layer MUST implement `EventPublisher` using AWS SQS.

#### Scenario: Debtor event published to SQS

- GIVEN a `DebtorProcessed` event
- WHEN `publishDebtorProcessed()` is called
- THEN the event MUST be serialized to JSON
- AND sent to the configured debtor SQS queue URL

#### Scenario: Entity event published to SQS

- GIVEN an `EntityProcessed` event
- WHEN `publishEntityProcessed()` is called
- THEN the event MUST be serialized to JSON
- AND sent to the configured entity SQS queue URL

#### Scenario: Import completed event published to SQS

- GIVEN an `ImportCompleted` event
- WHEN `publishImportCompleted()` is called
- THEN the event MUST be serialized to JSON
- AND sent to the configured import SQS queue URL

#### Scenario: SQS client uses endpoint override

- GIVEN `AWS_ENDPOINT_URL` is configured (LocalStack)
- WHEN the SQS client is initialized
- THEN it MUST use the endpoint URL override for all API calls

### REQ-EVT-006: LocalStack Setup Command

The system MUST provide a `localstack:setup` Artisan command that idempotently creates required AWS resources.

#### Scenario: Resources created

- GIVEN LocalStack is running at `localhost:4566`
- WHEN `php artisan localstack:setup` is executed
- THEN 1 S3 bucket MUST be created
- AND 3 SQS queues MUST be created (debtor, entity, import-completed)

#### Scenario: Idempotent execution

- GIVEN resources already exist from a previous run
- WHEN `php artisan localstack:setup` is re-executed
- THEN it MUST NOT fail
- AND existing resources MUST remain unchanged
