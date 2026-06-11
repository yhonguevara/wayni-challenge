<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Models\ImportLog;

/**
 * Port for ImportLog persistence operations.
 *
 * Infrastructure layer implements this interface using Eloquent.
 * Application layer depends on this interface, not the model directly.
 */
interface ImportLogRepository
{
    /**
     * Create a new ImportLog record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ImportLog;

    /**
     * Update an ImportLog's status and related fields.
     *
     * @param array<string, mixed> $data
     */
    public function updateStatus(string $importId, string $status, array $data = []): void;

    /**
     * Find an ImportLog by ID.
     */
    public function find(string $importId): ?ImportLog;

    /**
     * Return the ID of any import_log with status 'pending' or 'processing', or null if none.
     *
     * Used to enforce the single-active-import guard before dispatching a new job.
     */
    public function hasActiveImport(): ?string;
}
