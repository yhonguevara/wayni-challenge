<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Jobs;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\FileStorage;
use App\Application\Ports\ImportLogRepository;
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
        $fileStorage = $this->createMock(FileStorage::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $job = new ProcessBcraFile('/tmp/test.txt', (string) Str::uuid(), $fileStorage, $importLogRepository);

        // Assert
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff);
    }

    public function test_job_implements_should_queue(): void
    {
        // Arrange & Act
        $fileStorage = $this->createMock(FileStorage::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $job = new ProcessBcraFile('/tmp/test.txt', (string) Str::uuid(), $fileStorage, $importLogRepository);

        // Assert
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_failed_method_updates_import_log(): void
    {
        // Arrange
        $importId = (string) Str::uuid();
        $fileStorage = $this->createMock(FileStorage::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $importLogRepository->expects($this->once())
            ->method('updateStatus')
            ->with($importId, 'failed', $this->arrayHasKey('error_message'));

        $job = new ProcessBcraFile('/tmp/nonexistent.txt', $importId, $fileStorage, $importLogRepository);
        $exception = new \RuntimeException('File not found');

        // Act
        $job->failed($exception);
    }
}
