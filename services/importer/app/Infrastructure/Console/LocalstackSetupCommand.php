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
 * Creates: bcra-files S3 bucket and import-completed SQS queue.
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

        // Enable CORS so the browser can POST files directly via pre-signed URLs
        $this->configureCors($s3Client, $bucket);

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
        $this->createQueue($sqsClient, 'import-completed');

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

    /**
     * Enable CORS on the bucket so browsers can upload directly via pre-signed POST.
     *
     * Without this, the browser pre-flight (OPTIONS) and the POST are rejected by
     * S3/LocalStack with "CORS is not enabled for this bucket", which surfaces in the
     * frontend as "Network error during upload".
     */
    private function configureCors(S3Client $client, string $bucket): void
    {
        try {
            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => [
                    'CORSRules' => [
                        [
                            'AllowedOrigins' => ['*'],
                            'AllowedMethods' => ['GET', 'PUT', 'POST', 'HEAD'],
                            'AllowedHeaders' => ['*'],
                            'ExposeHeaders' => ['ETag'],
                            'MaxAgeSeconds' => 3000,
                        ],
                    ],
                ],
            ]);
            $this->info("  CORS enabled on bucket '{$bucket}'.");
        } catch (\Throwable $e) {
            $this->error("  Failed to enable CORS on bucket '{$bucket}': {$e->getMessage()}");
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
