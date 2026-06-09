<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\DTOs\DebtorProcessedEvent;
use App\Application\DTOs\EntityProcessedEvent;
use App\Application\DTOs\ImportCompletedEvent;
use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportCompletedHandler;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Custom SQS consumer for cross-service domain events.
 *
 * The importer publishes RAW domain-event JSON (not Laravel job envelopes), so
 * Laravel's `queue:work` cannot resolve a handler and silently drops the
 * messages. This command long-polls the three event queues, maps each raw JSON
 * body to its query-side DTO, and invokes the matching handler — keeping the two
 * services decoupled (they share an event contract, not Laravel job classes).
 */
final class ConsumeEventsCommand extends Command
{
    protected $signature = 'events:consume {--once : Process one poll cycle across all queues and exit}';

    protected $description = 'Consume cross-service domain events from SQS and persist them to the query store';

    /**
     * Long-poll wait time. Short so an empty queue doesn't stall the round-robin
     * across the other queues; the outer loop keeps polling regardless.
     */
    private const WAIT_TIME_SECONDS = 2;

    /** Messages to pull per receive call (SQS hard max). */
    private const MAX_MESSAGES = 10;

    public function handle(
        DebtorEventHandler $debtorHandler,
        EntityEventHandler $entityHandler,
        ImportCompletedHandler $importCompletedHandler,
    ): int {
        $client = $this->makeClient();
        $prefix = rtrim((string) config('queue.connections.sqs.prefix'), '/');

        $queues = [
            'debtor-events' => fn (array $b) => $debtorHandler->handle(DebtorProcessedEvent::fromArray($b)),
            'entity-events' => fn (array $b) => $entityHandler->handle(EntityProcessedEvent::fromArray($b)),
            'import-completed' => fn (array $b) => $importCompletedHandler->handle(
                ImportCompletedEvent::fromArray(self::mapImportCompleted($b)),
            ),
        ];

        $once = (bool) $this->option('once');
        $this->info('Consuming events from: ' . implode(', ', array_keys($queues)));

        do {
            foreach ($queues as $queueName => $handle) {
                $this->drainQueue($client, "{$prefix}/{$queueName}", $queueName, $handle);
            }
        } while (!$once);

        return self::SUCCESS;
    }

    /**
     * Drain one queue: keep receiving in batches until a receive comes back
     * empty, then return so the caller can move on to the next queue. In
     * `--once` mode only a single batch is processed per queue.
     *
     * @param callable(array<string,mixed>):void $handle
     */
    private function drainQueue(SqsClient $client, string $queueUrl, string $queueName, callable $handle): void
    {
        do {
            $result = $client->receiveMessage([
                'QueueUrl' => $queueUrl,
                'MaxNumberOfMessages' => self::MAX_MESSAGES,
                'WaitTimeSeconds' => self::WAIT_TIME_SECONDS,
            ]);

            $messages = $result['Messages'] ?? [];
            $this->processMessages($client, $queueUrl, $queueName, $messages, $handle);
        } while (!$this->option('once') && count($messages) > 0);
    }

    /**
     * @param array<int,array<string,mixed>> $messages
     * @param callable(array<string,mixed>):void $handle
     */
    private function processMessages(SqsClient $client, string $queueUrl, string $queueName, array $messages, callable $handle): void
    {
        foreach ($messages as $message) {
            $body = json_decode((string) ($message['Body'] ?? ''), true);

            if (!is_array($body)) {
                Log::warning('Skipping malformed event message', [
                    'queue' => $queueName,
                    'body' => $message['Body'] ?? null,
                ]);
                // Delete so a permanently-malformed message doesn't loop forever.
                $this->deleteMessage($client, $queueUrl, $message);
                continue;
            }

            try {
                $handle($body);
                $this->deleteMessage($client, $queueUrl, $message);
            } catch (\Throwable $e) {
                // Leave the message on the queue: SQS visibility timeout will
                // re-deliver it for retry rather than dropping the event.
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
            'QueueUrl' => $queueUrl,
            'ReceiptHandle' => (string) $message['ReceiptHandle'],
        ]);
    }

    /**
     * Map the importer's import-completed payload to the query DTO shape.
     *
     * The importer publishes {filename, totalDebtors, totalEntities, durationMs,
     * completedAt}; the query DTO expects {importId, filename, totalRecords,
     * validRecords, invalidRecords, durationMs}. We derive the record counts from
     * the debtor/entity totals (all published records are valid by construction).
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public static function mapImportCompleted(array $body): array
    {
        $total = (int) ($body['totalDebtors'] ?? 0) + (int) ($body['totalEntities'] ?? 0);

        return [
            'importId' => (string) ($body['importId'] ?? ''),
            'filename' => (string) ($body['filename'] ?? ''),
            'totalRecords' => $total,
            'validRecords' => $total,
            'invalidRecords' => 0,
            'durationMs' => (int) ($body['durationMs'] ?? 0),
        ];
    }

    private function makeClient(): SqsClient
    {
        return new SqsClient([
            'endpoint' => config('queue.connections.sqs.endpoint'),
            'region' => config('queue.connections.sqs.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('queue.connections.sqs.key'),
                'secret' => config('queue.connections.sqs.secret'),
            ],
        ]);
    }
}
