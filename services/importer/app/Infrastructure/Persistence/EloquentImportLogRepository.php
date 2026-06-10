<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\ImportLogRepository;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of ImportLogRepository.
 *
 * Wraps the ImportLog model for persistence operations.
 */
final class EloquentImportLogRepository implements ImportLogRepository
{
    public function __construct(
        private readonly ImportLog $model,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ImportLog
    {
        return $this->model->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(string $importId, string $status, array $data = []): void
    {
        $this->model->where('id', $importId)->update(
            array_merge(['status' => $status], $data)
        );
    }

    public function find(string $importId): ?ImportLog
    {
        return $this->model->find($importId);
    }

    /**
     * Set expected_records and transition status to 'persisting'.
     *
     * Guard: only updates rows whose status is NOT 'completed'.
     */
    public function setExpectedAndPersisting(string $importId, int $expected): void
    {
        DB::table('import_logs')
            ->where('id', $importId)
            ->where('status', '<>', 'completed')
            ->update([
                'expected_records' => $expected,
                'status'           => 'persisting',
            ]);
    }

    /**
     * Atomically increment persisted_records by 1.
     */
    public function incrementPersisted(string $importId): void
    {
        DB::table('import_logs')
            ->where('id', $importId)
            ->increment('persisted_records');
    }

    /**
     * Try to atomically mark the import as completed.
     *
     * Returns true ONLY if this caller's UPDATE affected exactly 1 row.
     */
    public function tryCompleteAndClaim(string $importId): bool
    {
        $affected = DB::table('import_logs')
            ->where('id', $importId)
            ->where('status', 'persisting')
            ->whereNotNull('expected_records')
            ->whereColumn('persisted_records', '>=', 'expected_records')
            ->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);

        return $affected === 1;
    }

    /**
     * Insert a ledger row for (importId, eventId).
     *
     * Returns true if inserted (first delivery).
     * Returns false when the composite (import_id, event_id) already exists.
     *
     * Uses INSERT ... ON CONFLICT DO NOTHING instead of catching an exception.
     * Catching UniqueConstraintViolationException inside a PostgreSQL transaction
     * still leaves the transaction in an aborted state (PostgreSQL's error protocol
     * marks the entire tx block as failed on any error, even if the application
     * catches the exception). ON CONFLICT DO NOTHING avoids the error entirely
     * and returns 0 affected rows for the duplicate case.
     */
    public function recordEventOnce(string $importId, string $eventId): bool
    {
        $affected = DB::affectingStatement(
            'INSERT INTO processed_events (import_id, event_id) VALUES (?, ?) ON CONFLICT (import_id, event_id) DO NOTHING',
            [$importId, $eventId],
        );

        return $affected === 1;
    }
}
