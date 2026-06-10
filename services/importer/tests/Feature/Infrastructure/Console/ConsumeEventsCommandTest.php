<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Console;

use App\Application\Notification\NotificationSender;
use App\Models\Debtor;
use App\Models\Entity;
use App\Models\ImportLog;
use Aws\Sqs\SqsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature test for ConsumeEventsCommand against LocalStack.
 *
 * Each test provisions its OWN uniquely-named queues and tears them down
 * afterwards — same isolation pattern as SqsEventPublisherTest. The command
 * accepts --debtor-queue-url / --entity-queue-url / --completed-queue-url
 * overrides so each test run targets its private queues.
 *
 * Requires: docker compose up -d localstack importer-db
 */
class ConsumeEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?SqsClient $client = null;

    /** Queue URLs created during a test, removed in tearDown. */
    private array $createdQueueUrls = [];

    private string $debtorQueueUrl     = '';
    private string $entityQueueUrl     = '';
    private string $importCompletedUrl = '';

    /** Monotonic suffix so each test gets its own queue names. */
    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = (string) env('AWS_ENDPOINT', 'http://localstack:4566');

        if (! $this->isLocalStackAvailable($endpoint)) {
            $this->markTestSkipped('LocalStack is not available at ' . $endpoint);
        }

        $this->client = new SqsClient([
            'endpoint'    => $endpoint,
            'region'      => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        $suffix = str_replace('.', '', uniqid((string) (++self::$seq) . '-', true));

        $this->debtorQueueUrl     = $this->createQueue("debtor-events-cmd-{$suffix}");
        $this->entityQueueUrl     = $this->createQueue("entity-events-cmd-{$suffix}");
        $this->importCompletedUrl = $this->createQueue("import-completed-cmd-{$suffix}");
    }

    protected function tearDown(): void
    {
        foreach ($this->createdQueueUrls as $url) {
            try {
                $this->client?->deleteQueue(['QueueUrl' => $url]);
            } catch (\Throwable) {
                // Best-effort — a failed delete must not fail the test.
            }
        }

        $this->createdQueueUrls = [];

        parent::tearDown();
    }

    /**
     * Consuming one poll cycle with 1 debtor + 1 entity + 1 import-completed
     * upserts both rows, moves the import_log to completed, and fires the
     * notification exactly once (sentinel last scenario).
     */
    public function test_consume_once_upserts_debtor_and_entity_and_fires_notification(): void
    {
        // Arrange — seed an import log so handlers can look it up
        $importId = (string) Str::uuid();
        ImportLog::create([
            'id'         => $importId,
            'filename'   => 'test.txt',
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        // Seed debtor-events queue
        $this->seedMessage($this->debtorQueueUrl, [
            'importId'             => $importId,
            'identificationNumber' => '20345678901',
            'maxSituation'         => '01',
            'totalLoans'           => 1500.0,
        ]);

        // Seed entity-events queue
        $this->seedMessage($this->entityQueueUrl, [
            'importId'   => $importId,
            'entityCode' => '00001',
            'totalLoans' => 5000.0,
        ]);

        // Seed import-completed queue (total=2 so sentinel fires after both records)
        $this->seedMessage($this->importCompletedUrl, [
            'importId'      => $importId,
            'totalDebtors'  => 1,
            'totalEntities' => 1,
        ]);

        // Bind a spy notification sender so we can assert exactly one send
        $notificationSpy = $this->createMock(NotificationSender::class);
        $notificationSpy->expects($this->once())->method('send');
        $this->app->instance(NotificationSender::class, $notificationSpy);

        // Act — --once exits after one poll cycle per queue
        $this->artisan('events:consume', [
            '--once'               => true,
            '--debtor-queue-url'   => $this->debtorQueueUrl,
            '--entity-queue-url'   => $this->entityQueueUrl,
            '--completed-queue-url' => $this->importCompletedUrl,
        ]);

        // Assert debtor upserted
        $this->assertDatabaseHas('debtors', [
            'identification_number' => '20345678901',
            'max_situation'         => '01',
        ]);

        // Assert entity upserted
        $this->assertDatabaseHas('entities', [
            'entity_code' => '00001',
        ]);

        // Assert import_log moved to completed
        $this->assertDatabaseHas('import_logs', [
            'id'     => $importId,
            'status' => 'completed',
        ]);
    }

    /**
     * A malformed (non-JSON) body is deleted from the queue without throwing,
     * so the consumer does not stall on a permanently-bad message.
     */
    public function test_consume_once_deletes_malformed_message_without_throwing(): void
    {
        // Arrange — seed a malformed message on the debtor queue
        $this->client->sendMessage([
            'QueueUrl'    => $this->debtorQueueUrl,
            'MessageBody' => 'not-valid-json',
        ]);

        // Act — should not throw
        $this->artisan('events:consume', [
            '--once'               => true,
            '--debtor-queue-url'   => $this->debtorQueueUrl,
            '--entity-queue-url'   => $this->entityQueueUrl,
            '--completed-queue-url' => $this->importCompletedUrl,
        ]);

        // Assert the queue is now empty (message was deleted, not left for retry)
        $result = $this->client->receiveMessage([
            'QueueUrl'            => $this->debtorQueueUrl,
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds'     => 1,
        ]);

        $this->assertEmpty($result['Messages'] ?? []);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     */
    private function seedMessage(string $queueUrl, array $payload): void
    {
        $this->client->sendMessage([
            'QueueUrl'    => $queueUrl,
            'MessageBody' => (string) json_encode($payload),
        ]);
    }

    private function createQueue(string $name): string
    {
        $url = $this->client->createQueue(['QueueName' => $name])['QueueUrl'];
        $this->createdQueueUrls[] = $url;

        return $url;
    }

    private function isLocalStackAvailable(string $endpoint): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 2,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint . '/_localstack/health', false, $context);

        return $response !== false;
    }
}
