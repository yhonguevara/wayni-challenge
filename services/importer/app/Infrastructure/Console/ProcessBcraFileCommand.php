<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\ImportLogRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Artisan command to process BCRA files.
 *
 * Validates the file exists, checks for active imports (Item 3 guard),
 * creates an ImportLog, and dispatches the ProcessBcraFile job for async processing.
 */
final class ProcessBcraFileCommand extends Command
{
    protected $signature = 'bcra:process {path : Path to the BCRA deudores.txt file}';

    protected $description = 'Process a BCRA deudores file and import data';

    public function __construct(
        private readonly ImportLogRepository $importLogRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        // Validate file exists and is readable
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        if (!is_readable($path)) {
            $this->error("File is not readable: {$path}");

            return self::FAILURE;
        }

        // Single-active-import guard (Item 3).
        $activeId = $this->importLogRepository->hasActiveImport();
        if ($activeId !== null) {
            $this->error("An import is already in progress (import_log_id: {$activeId}). Wait for it to complete before starting a new one.");

            return self::FAILURE;
        }

        // Generate import ID
        $importId = (string) Str::uuid();

        // Create ImportLog (status: pending)
        $this->importLogRepository->create([
            'id'       => $importId,
            'filename' => basename($path),
            'status'   => 'pending',
        ]);

        // Dispatch job
        ProcessBcraFile::dispatch($path, $importId);

        $this->info('File queued for processing.');
        $this->info("Import ID: {$importId}");
        $this->line('Track progress with: php artisan tinker --execute="App\\Models\\ImportLog::find(\'' . $importId . '\')"');

        return self::SUCCESS;
    }
}
