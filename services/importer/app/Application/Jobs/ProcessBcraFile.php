<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\FileStorage;
use App\Application\Ports\ImportLogRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job for processing BCRA files.
 *
 * Wraps ImportOrchestrator for async execution with retry logic.
 * Supports dual-mode file handling:
 * - Local filesystem path: process directly
 * - S3 key: download to temp, process, delete temp
 */
final class ProcessBcraFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: 10s, 30s, 90s.
     */
    public array $backoff = [10, 30, 90];

    /**
     * Create a new job instance.
     *
     * @param string $fileSource Local filesystem path OR S3 key
     * @param string $importId Import log identifier
     */
    public function __construct(
        private readonly string $fileSource,
        private readonly string $importId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FileStorage $fileStorage): void
    {
        $tempFile = null;

        try {
            // Determine source type and get local file path
            $filePath = $this->resolveFilePath($tempFile, $fileStorage);

            // Resolve orchestrator from container
            $orchestrator = app(ImportOrchestrator::class);

            // Execute pipeline
            $orchestrator->orchestrate($filePath, $this->importId);
        } finally {
            // Clean up temp file if downloaded from S3
            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBcraFile job failed', [
            'import_id' => $this->importId,
            'file_source' => $this->fileSource,
            'error' => $exception->getMessage(),
        ]);

        // Update ImportLog status to failed
        $importLogRepository = app(ImportLogRepository::class);
        $importLogRepository->updateStatus($this->importId, 'failed', [
            'finished_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Resolve the file path, downloading from S3 if needed.
     *
     * Detection logic: if $fileSource starts with '/' or file_exists(), treat as local;
     * otherwise treat as S3 key.
     */
    private function resolveFilePath(?string &$tempFile, FileStorage $fileStorage): string
    {
        // Local filesystem path
        if (str_starts_with($this->fileSource, '/') || file_exists($this->fileSource)) {
            return $this->fileSource;
        }

        // S3 key — download to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'bcra_import_');
        $fileStorage->download($this->fileSource, $tempFile);

        return $tempFile;
    }
}
