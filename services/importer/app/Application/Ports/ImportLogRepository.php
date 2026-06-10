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
     * Set expected_records and transition status to 'persisting'.
     *
     * Guard: only updates rows whose status is NOT 'completed'.
     * Idempotent if called multiple times before completion.
     */
    public function setExpectedAndPersisting(string $importId, int $expected): void;

    /**
     * Atomically increment persisted_records by 1.
     */
    public function incrementPersisted(string $importId): void;

    /**
     * Try to atomically mark the import as completed.
     *
     * Issues a conditional UPDATE:
     *   SET status='completed', finished_at=now()
     *   WHERE id=? AND status='persisting'
     *         AND expected_records IS NOT NULL
     *         AND persisted_records >= expected_records
     *
     * Returns true ONLY if affected_rows === 1 (this caller is the sole claimer).
     * Returns false when the import is already completed, counts don't match, or
     * expected_records is still NULL.
     */
    public function tryCompleteAndClaim(string $importId): bool;

    /**
     * Insert a ledger row for (importId, eventId).
     *
     * Returns true if the row was inserted (first delivery).
     * Returns false if the composite unique constraint fires (duplicate delivery).
     * The unique-violation exception MUST NOT bubble to the caller.
     */
    public function recordEventOnce(string $importId, string $eventId): bool;
}
