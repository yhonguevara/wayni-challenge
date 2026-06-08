<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;

/**
 * Artisan command to set up LocalStack resources (S3 bucket + SQS queues).
 *
 * Idempotent — safe to run multiple times.
 * Creates: bcra-files S3 bucket, debtor-events and entity-events SQS queues.
 */
final class LocalstackSetupCommand extends Command
{
    protected $signature = 'localstack:setup';

    protected $description = 'Create S3 bucket and SQS queues in LocalStack (idempotent)';

    public function handle(): int
    {
        $endpoint = config('s3.endpoint', 'http://localstack:4566');
        $region = config('s3.region', 'us-east-1');
        $bucket = config('s3.bucket', 'bcra-files');

        $this->info("Setting up LocalStack resources at {$endpoint}...");

        // Create S3 client
        $s3Client = new S3Client([
            'endpoint' => $endpoint,
            'region' => $region,
            'version' => 'latest',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        // Create S3 bucket (idempotent)
        $this->createBucket($s3Client, $bucket);

        // Create SQS client
        $sqsClient = new SqsClient([
            'endpoint' => $endpoint,
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        // Create SQS queues (idempotent)
        $this->createQueue($sqsClient, 'debtor-events');
        $this->createQueue($sqsClient, 'entity-events');

        $this->info('LocalStack setup complete!');

        return self::SUCCESS;
    }

    private function createBucket(S3Client $client, string $bucket): void
    {
        try {
            if ($this->bucketExists($client, $bucket)) {
                $this->line("  S3 bucket '{$bucket}' already exists — skipping.");

                return;
            }

            $client->createBucket(['Bucket' => $bucket]);
            $this->info("  S3 bucket '{$bucket}' created.");
        } catch (\Throwable $e) {
            $this->error("  Failed to create S3 bucket '{$bucket}': {$e->getMessage()}");
        }
    }

    private function bucketExists(S3Client $client, string $bucket): bool
    {
        try {
            $client->headBucket(['Bucket' => $bucket]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function createQueue(SqsClient $client, string $queueName): void
    {
        try {
            $result = $client->createQueue(['QueueName' => $queueName]);
            $queueUrl = $result['QueueUrl'] ?? 'unknown';
            $this->info("  SQS queue '{$queueName}' ready at {$queueUrl}.");
        } catch (\Throwable $e) {
            $this->error("  Failed to create SQS queue '{$queueName}': {$e->getMessage()}");
        }
    }
}
