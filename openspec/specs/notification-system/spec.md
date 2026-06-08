# Notification System Specification

## Purpose

Multi-channel import completion notification driven by environment configuration.

## Requirements

### REQ-NTF-001: NotificationSender Interface

The system MUST define a `NotificationSender` interface in the Application layer.

#### Scenario: Interface contract

- GIVEN the `NotificationSender` interface is inspected
- WHEN its methods are read
- THEN it MUST declare `send(ImportCompleted $event): void`
- AND it MUST NOT import Infrastructure or Laravel classes

### REQ-NTF-002: LogNotification

The system MUST provide a `LogNotification` implementation that writes structured JSON via `Log::info()`.

#### Scenario: Structured log output

- GIVEN an `ImportCompleted` event is received
- WHEN `LogNotification::send()` is called
- THEN it MUST log a JSON structure containing filename, totals, and duration
- AND the log entry MUST be level `info`

### REQ-NTF-003: WebhookNotification

The system MUST provide a `WebhookNotification` that sends an HTTP POST to a configured URL.

#### Scenario: Webhook delivery

- GIVEN `NOTIFICATION_WEBHOOK_URL` is configured
- WHEN `WebhookNotification::send()` is called
- THEN it MUST POST the event payload as JSON to the configured URL

#### Scenario: Webhook URL not configured

- GIVEN `NOTIFICATION_WEBHOOK_URL` is empty or missing
- WHEN `WebhookNotification` is instantiated
- THEN it MUST throw a configuration exception

### REQ-NTF-004: SqsNotification

The system MUST provide an `SqsNotification` that publishes to a notifications SQS queue.

#### Scenario: SQS publish

- GIVEN an `ImportCompleted` event is received
- WHEN `SqsNotification::send()` is called
- THEN it MUST serialize the event and publish to the configured notifications queue

### REQ-NTF-005: NotificationFactory

The system MUST provide a `NotificationFactory` that resolves the implementation based on `NOTIFICATION_DRIVER`.

#### Scenario: Driver resolution

- GIVEN `NOTIFICATION_DRIVER` is set to `log`, `webhook`, or `sqs`
- WHEN the factory is invoked
- THEN it MUST return the corresponding `NotificationSender` implementation

#### Scenario: Default driver

- GIVEN `NOTIFICATION_DRIVER` is not set
- WHEN the factory is invoked
- THEN it MUST return `LogNotification` as the default

#### Scenario: Invalid driver

- GIVEN `NOTIFICATION_DRIVER` is set to an unknown value
- WHEN the factory is invoked
- THEN it MUST throw an `InvalidArgumentException`
