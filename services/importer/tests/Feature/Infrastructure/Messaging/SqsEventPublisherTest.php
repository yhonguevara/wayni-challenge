<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Messaging;

use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Messaging\SqsEventPublisher;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for SqsEventPublisher against LocalStack.
 *
 * Requires: docker compose up -d localstack
 * Run: php artisan test --filter=SqsEventPublisherTest
 *
 * Each test provisions its OWN uniquely-named queues and tears them down
 * afterwards. This guarantees full isolation: no shared state, no reliance on
 * SQS PurgeQueue (which is asynchronous and can take up to 60s), so the suite
 * is deterministic whether run alone or alongside every other test.
 */
class SqsEventPublisherTest extends TestCase
{
    private ?SqsClient $client = null;
    private string $debtorQueueUrl = '';
    private string $entityQueueUrl = '';
    private string $importCompletedQueueUrl = '';

    /** Queue URLs created during a test, removed in tearDown. */
    private array $createdQueueUrls = [];

    /** Monotonic suffix so each test gets its own queue names. */
    private static int $queueSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = $this->resolveEndpoint();

        // Skip only when LocalStack genuinely isn't reachable (e.g. CI without it).
        if (!$this->isLocalStackAvailable($endpoint)) {
            $this->markTestSkipped('LocalStack is not available at ' . $endpoint);
        }

        $this->client = new SqsClient([
            'endpoint' => $endpoint,
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        $suffix = uniqid((string) (++self::$queueSeq) . '-', true);
        $suffix = str_replace('.', '', $suffix);

        $this->debtorQueueUrl = $this->createQueue("debtor-events-test-{$suffix}");
        $this->entityQueueUrl = $this->createQueue("entity-events-test-{$suffix}");
        $this->importCompletedQueueUrl = $this->createQueue("import-completed-test-{$suffix}");
    }

    protected function tearDown(): void
    {
        foreach ($this->createdQueueUrls as $url) {
            try {
                $this->client?->deleteQueue(['QueueUrl' => $url]);
            } catch (\Throwable) {
                // Best-effort cleanup — a failed delete must not fail the test.
            }
        }

        $this->createdQueueUrls = [];

        parent::tearDown();
    }

    public function test_publish_debtor_processed_event(): void
    {
        // Arrange
        $publisher = $this->makePublisher();

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'test-import-001',
        );

        // Act
        $publisher->publishDebtorProcessed($event);

        // Assert — message should be in debtor queue
        $messages = $this->drainQueue($this->debtorQueueUrl, 1);

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $body = json_decode($message['Body'], true);

        $this->assertSame('20345123458', $body['identificationNumber']);
        $this->assertSame('05', $body['maxSituation']);
        $this->assertSame(1500.5, $body['totalLoans']);
        $this->assertSame('DebtorProcessed', $message['MessageAttributes']['event_type']['StringValue']);
    }

    public function test_publish_entity_processed_event(): void
    {
        // Arrange
        $publisher = $this->makePublisher();

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 5000.0,
            importId: 'test-import-001',
        );

        // Act
        $publisher->publishEntityProcessed($event);

        // Assert — message should be in entity queue
        $messages = $this->drainQueue($this->entityQueueUrl, 1);

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $body = json_decode($message['Body'], true);

        $this->assertSame('00001', $body['entityCode']);
        $this->assertEquals(5000.0, $body['totalLoans']);
        $this->assertSame('EntityProcessed', $message['MessageAttributes']['event_type']['StringValue']);
    }

    public function test_publish_import_completed_event(): void
    {
        // Arrange
        $publisher = $this->makePublisher();

        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable('2026-06-08T12:00:00Z'),
        );

        // Act
        $publisher->publishImportCompleted($event);

        // Assert — message should be in import-completed queue
        $messages = $this->drainQueue($this->importCompletedQueueUrl, 1);

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $body = json_decode($message['Body'], true);

        $this->assertSame('deudores.txt', $body['filename']);
        $this->assertSame(150, $body['totalDebtors']);
        $this->assertSame(5, $body['totalEntities']);
        $this->assertSame(2500, $body['durationMs']);
        $this->assertSame('ImportCompleted', $message['MessageAttributes']['event_type']['StringValue']);
    }

    public function test_publish_batch_events(): void
    {
        // Arrange
        $publisher = $this->makePublisher();

        $events = [
            new DebtorProcessed('20345123458', '01', 100.0, 'import-001'),
            new DebtorProcessed('20345123459', '03', 200.0, 'import-001'),
            new EntityProcessed('00001', 500.0, 'import-001'),
        ];

        // Act
        $publisher->publishBatch($events);

        // Assert — 2 messages in debtor queue, 1 in entity queue
        $this->assertCount(2, $this->drainQueue($this->debtorQueueUrl, 2));
        $this->assertCount(1, $this->drainQueue($this->entityQueueUrl, 1));
    }

    public function test_publish_batch_with_more_than_10_events(): void
    {
        // Arrange
        $publisher = $this->makePublisher();

        $events = [];
        for ($i = 0; $i < 15; $i++) {
            $events[] = new DebtorProcessed(
                sprintf('20345123%03d', $i),
                '01',
                100.0 * ($i + 1),
                'import-001',
            );
        }

        // Act
        $publisher->publishBatch($events);

        // Assert — all 15 messages should arrive (batches split at 10 internally)
        $this->assertCount(15, $this->drainQueue($this->debtorQueueUrl, 15));
    }

    private function makePublisher(): SqsEventPublisher
    {
        return new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );
    }

    /**
     * Create a queue and register it for teardown.
     */
    private function createQueue(string $name): string
    {
        $url = $this->client->createQueue(['QueueName' => $name])['QueueUrl'];
        $this->createdQueueUrls[] = $url;

        return $url;
    }

    /**
     * Drain up to $expected messages from a queue, polling until the count is
     * reached or the receive calls stop returning anything.
     *
     * SQS does not guarantee a single ReceiveMessage returns every available
     * message, so we loop. Bounded attempts keep a genuinely empty queue from
     * hanging the test.
     *
     * @return array<int, array<string, mixed>>
     */
    private function drainQueue(string $queueUrl, int $expected): array
    {
        $messages = [];
        $emptyPolls = 0;

        while (count($messages) < $expected && $emptyPolls < 3) {
            $result = $this->client->receiveMessage([
                'QueueUrl' => $queueUrl,
                'MaxNumberOfMessages' => 10,
                'WaitTimeSeconds' => 1,
                'MessageAttributeNames' => ['All'],
            ]);

            $batch = $result['Messages'] ?? [];

            if (empty($batch)) {
                $emptyPolls++;
                continue;
            }

            $emptyPolls = 0;

            foreach ($batch as $message) {
                $messages[] = $message;
                $this->client->deleteMessage([
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ]);
            }
        }

        return $messages;
    }

    private function resolveEndpoint(): string
    {
        return env('AWS_ENDPOINT', 'http://localstack:4566');
    }

    private function isLocalStackAvailable(string $endpoint): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint . '/_localstack/health', false, $context);

        return $response !== false;
    }
}
