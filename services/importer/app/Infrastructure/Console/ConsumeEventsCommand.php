<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\DTOs\DebtorProcessedDTO;
use App\Application\DTOs\EntityProcessedDTO;
use App\Application\DTOs\ImportCompletedDTO;
use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportCompletedHandler;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Custom SQS consumer for importer-side domain events.
 *
 * The importer publishes RAW domain-event JSON (not Laravel job envelopes), so
 * Laravel's `queue:work` cannot resolve a handler and would silently drop
 * messages. This command long-polls the three event queues in a round-robin,
 * maps each raw JSON body to the appropriate DTO, and invokes the matching
 * handler — same proven pattern as the query-side ConsumeEventsCommand.
 *
 * Queue URL resolution (in order of precedence):
 *   1. Explicit --*-queue-url options (used by tests for isolation).
 *   2. SQS prefix from config('queue.connections.sqs.prefix') + queue name.
 */
final class ConsumeEventsCommand extends Command
{
    protected $signature = 'events:consume
        {--once : Process one poll cycle across all queues and exit}
        {--debtor-queue-url= : Override debtor-events queue URL (for testing)}
        {--entity-queue-url= : Override entity-events queue URL (for testing)}
        {--completed-queue-url= : Override import-completed queue URL (for testing)}';

    protected $description = 'Consume importer domain events from SQS and persist them to the database';

    /** Long-poll wait time per receive call. Short so the round-robin stays responsive. */
    private const WAIT_TIME_SECONDS = 2;

    /** Maximum messages per receive call (SQS hard limit). */
    private const MAX_MESSAGES = 10;

    public function handle(
        DebtorEventHandler $debtorHandler,
        EntityEventHandler $entityHandler,
        ImportCompletedHandler $importCompletedHandler,
    ): int {
        $client = $this->makeClient();
        $prefix = rtrim((string) config('queue.connections.sqs.prefix'), '/');

        $queues = [
            'debtor-events' => [
                'url'    => (string) ($this->option('debtor-queue-url') ?: "{$prefix}/debtor-events"),
                'handle' => fn (array $b) => $debtorHandler->handle(DebtorProcessedDTO::fromArray($b)),
            ],
            'entity-events' => [
                'url'    => (string) ($this->option('entity-queue-url') ?: "{$prefix}/entity-events"),
                'handle' => fn (array $b) => $entityHandler->handle(EntityProcessedDTO::fromArray($b)),
            ],
            'import-completed' => [
                'url'    => (string) ($this->option('completed-queue-url') ?: "{$prefix}/import-completed"),
                'handle' => fn (array $b) => $importCompletedHandler->handle(ImportCompletedDTO::fromArray($b)),
            ],
        ];

        $once = (bool) $this->option('once');
        $this->info('Consuming events from: ' . implode(', ', array_keys($queues)));

        do {
            foreach ($queues as $queueName => $queue) {
                $this->drainQueue($client, $queue['url'], $queueName, $queue['handle']);
            }
        } while (! $once);

        return self::SUCCESS;
    }

    /**
     * Drain one queue: receive in batches until empty, then move on.
     * In --once mode a single batch per queue is processed.
     *
     * @param callable(array<string,mixed>):void $handle
     */
    private function drainQueue(SqsClient $client, string $queueUrl, string $queueName, callable $handle): void
    {
        do {
            $result = $client->receiveMessage([
                'QueueUrl'            => $queueUrl,
                'MaxNumberOfMessages' => self::MAX_MESSAGES,
                'WaitTimeSeconds'     => self::WAIT_TIME_SECONDS,
            ]);

            $messages = $result['Messages'] ?? [];
            $this->processMessages($client, $queueUrl, $queueName, $messages, $handle);
        } while (! $this->option('once') && count($messages) > 0);
    }

    /**
     * @param array<int,array<string,mixed>>    $messages
     * @param callable(array<string,mixed>):void $handle
     */
    private function processMessages(
        SqsClient $client,
        string $queueUrl,
        string $queueName,
        array $messages,
        callable $handle,
    ): void {
        foreach ($messages as $message) {
            $body = json_decode((string) ($message['Body'] ?? ''), true);

            if (! is_array($body)) {
                Log::warning('Skipping malformed event message', [
                    'queue' => $queueName,
                    'body'  => $message['Body'] ?? null,
                ]);
                // Delete permanently-malformed messages so they don't loop forever.
                $this->deleteMessage($client, $queueUrl, $message);
                continue;
            }

            try {
                $handle($body);
                $this->deleteMessage($client, $queueUrl, $message);
            } catch (\Throwable $e) {
                // Leave the message on the queue: SQS visibility timeout will
                // re-deliver it for retry rather than silently dropping the event.
                Log::error('Failed to process event', [
                    'queue' => $queueName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $message
     */
    private function deleteMessage(SqsClient $client, string $queueUrl, array $message): void
    {
        $client->deleteMessage([
            'QueueUrl'      => $queueUrl,
            'ReceiptHandle' => (string) $message['ReceiptHandle'],
        ]);
    }

    private function makeClient(): SqsClient
    {
        return new SqsClient([
            'endpoint'    => (string) env('AWS_ENDPOINT', 'http://localstack:4566'),
            'region'      => (string) env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => (string) env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => (string) env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);
    }
}
