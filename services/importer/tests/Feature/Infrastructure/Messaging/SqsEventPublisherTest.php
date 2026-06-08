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
 * Requires: docker-compose up -d localstack
 * Run: php artisan test --filter=SqsEventPublisherTest
 */
class SqsEventPublisherTest extends TestCase
{
    private ?SqsClient $client = null;
    private string $debtorQueueUrl = '';
    private string $entityQueueUrl = '';
    private string $importCompletedQueueUrl = '';

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = env('AWS_ENDPOINT', 'http://localhost:4566');

        // Skip if LocalStack is not available
        if (!$this->isLocalStackAvailable($endpoint)) {
            $this->markTestSkipped('LocalStack is not available at ' . $endpoint);
        }

        $this->client = new SqsClient([
            'endpoint' => $endpoint,
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        // Ensure queues exist
        $debtorResult = $this->client->createQueue(['QueueName' => 'debtor-events']);
        $this->debtorQueueUrl = $debtorResult['QueueUrl'];

        $entityResult = $this->client->createQueue(['QueueName' => 'entity-events']);
        $this->entityQueueUrl = $entityResult['QueueUrl'];

        $importResult = $this->client->createQueue(['QueueName' => 'import-completed']);
        $this->importCompletedQueueUrl = $importResult['QueueUrl'];

        // Purge queues before each test
        $this->client->purgeQueue(['QueueUrl' => $this->debtorQueueUrl]);
        $this->client->purgeQueue(['QueueUrl' => $this->entityQueueUrl]);
        $this->client->purgeQueue(['QueueUrl' => $this->importCompletedQueueUrl]);
    }

    public function test_publish_debtor_processed_event(): void
    {
        // Arrange
        $publisher = new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'test-import-001',
        );

        // Act
        $publisher->publishDebtorProcessed($event);

        // Assert — message should be in debtor queue
        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->debtorQueueUrl,
            'MaxNumberOfMessages' => 10,
            'MessageAttributeNames' => ['All'],
        ]);

        $this->assertNotEmpty($result['Messages']);
        $message = $result['Messages'][0];
        $body = json_decode($message['Body'], true);

        $this->assertSame('20345123458', $body['identificationNumber']);
        $this->assertSame('05', $body['maxSituation']);
        $this->assertSame(1500.5, $body['totalLoans']);
        $this->assertSame('DebtorProcessed', $message['MessageAttributes']['event_type']['StringValue']);
    }

    public function test_publish_entity_processed_event(): void
    {
        // Arrange
        $publisher = new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 5000.0,
            importId: 'test-import-001',
        );

        // Act
        $publisher->publishEntityProcessed($event);

        // Assert — message should be in entity queue
        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->entityQueueUrl,
            'MaxNumberOfMessages' => 10,
            'MessageAttributeNames' => ['All'],
        ]);

        $this->assertNotEmpty($result['Messages']);
        $message = $result['Messages'][0];
        $body = json_decode($message['Body'], true);

        $this->assertSame('00001', $body['entityCode']);
        $this->assertSame(5000.0, $body['totalLoans']);
        $this->assertSame('EntityProcessed', $message['MessageAttributes']['event_type']['StringValue']);
    }

    public function test_publish_import_completed_event(): void
    {
        // Arrange
        $publisher = new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );

        $event = new ImportCompleted(
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable('2026-06-08T12:00:00Z'),
        );

        // Act
        $publisher->publishImportCompleted($event);

        // Assert — message should be in import-completed queue
        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->importCompletedQueueUrl,
            'MaxNumberOfMessages' => 10,
            'MessageAttributeNames' => ['All'],
        ]);

        $this->assertNotEmpty($result['Messages']);
        $message = $result['Messages'][0];
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
        $publisher = new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );

        $events = [
            new DebtorProcessed('20345123458', '01', 100.0, 'import-001'),
            new DebtorProcessed('20345123459', '03', 200.0, 'import-001'),
            new EntityProcessed('00001', 500.0, 'import-001'),
        ];

        // Act
        $publisher->publishBatch($events);

        // Assert — 2 messages in debtor queue, 1 in entity queue
        $debtorResult = $this->client->receiveMessage([
            'QueueUrl' => $this->debtorQueueUrl,
            'MaxNumberOfMessages' => 10,
        ]);
        $this->assertCount(2, $debtorResult['Messages']);

        $entityResult = $this->client->receiveMessage([
            'QueueUrl' => $this->entityQueueUrl,
            'MaxNumberOfMessages' => 10,
        ]);
        $this->assertCount(1, $entityResult['Messages']);
    }

    public function test_publish_batch_with_more_than_10_events(): void
    {
        // Arrange
        $publisher = new SqsEventPublisher(
            $this->client,
            $this->debtorQueueUrl,
            $this->entityQueueUrl,
            $this->importCompletedQueueUrl,
        );

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

        // Assert — all 15 messages should arrive
        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->debtorQueueUrl,
            'MaxNumberOfMessages' => 10,
        ]);
        $this->assertCount(10, $result['Messages']);

        $result2 = $this->client->receiveMessage([
            'QueueUrl' => $this->debtorQueueUrl,
            'MaxNumberOfMessages' => 10,
        ]);
        $this->assertCount(5, $result2['Messages']);
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
