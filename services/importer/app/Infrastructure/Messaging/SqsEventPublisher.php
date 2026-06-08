<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Ports\EventPublisher;
use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\DomainEvent;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use Aws\Sqs\SqsClient;

/**
 * SQS implementation of the EventPublisher port.
 *
 * Routes events to appropriate queues based on event type.
 * Uses MessageAttributes for SQS filtering.
 */
final class SqsEventPublisher implements EventPublisher
{
    private const MAX_BATCH_SIZE = 10;

    public function __construct(
        private readonly SqsClient $client,
        private readonly string $debtorQueueUrl,
        private readonly string $entityQueueUrl,
        private readonly string $importCompletedQueueUrl,
    ) {}

    /**
     * Publish a DebtorProcessed event to the debtor SQS queue.
     */
    public function publishDebtorProcessed(DebtorProcessed $event): void
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->debtorQueueUrl,
            'MessageBody' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'MessageAttributes' => [
                'event_type' => [
                    'DataType' => 'String',
                    'StringValue' => 'DebtorProcessed',
                ],
            ],
        ]);
    }

    /**
     * Publish an EntityProcessed event to the entity SQS queue.
     */
    public function publishEntityProcessed(EntityProcessed $event): void
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->entityQueueUrl,
            'MessageBody' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'MessageAttributes' => [
                'event_type' => [
                    'DataType' => 'String',
                    'StringValue' => 'EntityProcessed',
                ],
            ],
        ]);
    }

    /**
     * Publish an ImportCompleted event to the import-completed SQS queue.
     */
    public function publishImportCompleted(ImportCompleted $event): void
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->importCompletedQueueUrl,
            'MessageBody' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'MessageAttributes' => [
                'event_type' => [
                    'DataType' => 'String',
                    'StringValue' => 'ImportCompleted',
                ],
            ],
        ]);
    }

    /**
     * Publish a batch of domain events.
     *
     * SQS supports up to 10 messages per batch. Larger batches are split.
     *
     * @param array<int, DomainEvent> $events
     */
    public function publishBatch(array $events): void
    {
        $batches = array_chunk($events, self::MAX_BATCH_SIZE);

        foreach ($batches as $batch) {
            $this->publishBatchChunk($batch);
        }
    }

    /**
     * Publish a single batch chunk (up to 10 messages).
     *
     * @param array<int, DomainEvent> $batch
     */
    private function publishBatchChunk(array $batch): void
    {
        // Group by queue URL for efficient batching
        /** @var array<string, array<int, DomainEvent>> $grouped */
        $grouped = [];

        foreach ($batch as $event) {
            $queueUrl = $this->resolveQueueUrl($event);
            $grouped[$queueUrl][] = $event;
        }

        foreach ($grouped as $queueUrl => $events) {
            $entries = [];

            foreach ($events as $i => $event) {
                $entries[] = [
                    'Id' => (string) $i,
                    'MessageBody' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
                    'MessageAttributes' => [
                        'event_type' => [
                            'DataType' => 'String',
                            'StringValue' => $this->resolveEventTypeName($event),
                        ],
                    ],
                ];
            }

            $result = $this->client->sendMessageBatch([
                'QueueUrl' => $queueUrl,
                'Entries' => $entries,
            ]);

            // Handle batch failures
            if (!empty($result['Failed'])) {
                $failedIds = array_column($result['Failed'], 'Id');
                error_log(sprintf(
                    'SQS batch publish: %d messages failed: %s',
                    count($failedIds),
                    implode(', ', $failedIds),
                ));
            }
        }
    }

    /**
     * Resolve the SQS queue URL for a given event type.
     */
    private function resolveQueueUrl(DomainEvent $event): string
    {
        return match ($event::class) {
            DebtorProcessed::class => $this->debtorQueueUrl,
            EntityProcessed::class => $this->entityQueueUrl,
            ImportCompleted::class => $this->importCompletedQueueUrl,
            default => throw new \InvalidArgumentException(
                sprintf('Unknown event type: %s', $event::class),
            ),
        };
    }

    /**
     * Get the event type name for MessageAttributes.
     */
    private function resolveEventTypeName(DomainEvent $event): string
    {
        return match ($event::class) {
            DebtorProcessed::class => 'DebtorProcessed',
            EntityProcessed::class => 'EntityProcessed',
            ImportCompleted::class => 'ImportCompleted',
            default => throw new \InvalidArgumentException(
                sprintf('Unknown event type: %s', $event::class),
            ),
        };
    }
}
