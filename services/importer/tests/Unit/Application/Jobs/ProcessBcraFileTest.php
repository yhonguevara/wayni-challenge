<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Jobs;

use App\Application\Jobs\ProcessBcraFile;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessBcraFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_retry_configuration(): void
    {
        // Arrange & Act
        $job = new ProcessBcraFile('/tmp/test.txt', (string) Str::uuid());

        // Assert
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff);
    }

    public function test_job_implements_should_queue(): void
    {
        // Arrange & Act
        $job = new ProcessBcraFile('/tmp/test.txt', (string) Str::uuid());

        // Assert
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_failed_method_updates_import_log(): void
    {
        // Arrange
        $importId = (string) Str::uuid();
        ImportLog::create([
            'id' => $importId,
            'filename' => 'test.txt',
            'status' => 'processing',
        ]);

        $job = new ProcessBcraFile('/tmp/nonexistent.txt', $importId);
        $exception = new \RuntimeException('File not found');

        // Act
        $job->failed($exception);

        // Assert
        $importLog = ImportLog::find($importId);
        $this->assertNotNull($importLog);
        $this->assertSame('failed', $importLog->status);
        $this->assertSame('File not found', $importLog->error_message);
        $this->assertNotNull($importLog->finished_at);
    }
}
