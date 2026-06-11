<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\EntityProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Handles EntityProcessed events arriving from SQS.
 *
 * STREAMING-PURE design: each event carries ONE raw file line's loan amount.
 * The handler performs an ADDITIVE upsert so the database aggregates:
 *   total_loan_amount = SUM of all delivered loan amounts for the entity code.
 *
 * Within ONE DB transaction:
 *   1. Insert a ledger row via recordEventOnce — if duplicate, abort (idempotent).
 *   2. ADDITIVE upsert the entity row (ON CONFLICT DO UPDATE with SUM).
 *   3. Atomically increment persisted_records.
 *
 * After the transaction commits:
 *   4. Run the guarded tryCompleteAndClaim — fires notification exactly once.
 *
 * Event ID = sha256("{importId}|entity|{lineNumber}") — per LINE, not per entity code,
 * because many events share the same entity code in the streaming design. Same lineNumber
 * re-delivered → idempotent (no double-count, no double-add).
 */
final class UpsertEntityHandler implements EntityEventHandler
{
    public function __construct(
        private readonly ImportLogRepository $repository,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function handle(EntityProcessedDTO $dto): void
    {
        // Per-line event ID — same entity code in different lines must NOT share the same ID
        $eventId = hash('sha256', $dto->importId . '|entity|' . $dto->lineNumber);

        DB::transaction(function () use ($dto, $eventId): void {
            if (! $this->repository->recordEventOnce($dto->importId, $eventId)) {
                return; // duplicate delivery — idempotent no-op
            }

            $now = now()->format('Y-m-d H:i:s');

            // ADDITIVE upsert: SUM loan amounts on conflict
            DB::statement(
                "INSERT INTO entities (entity_code, total_loan_amount, created_at, updated_at)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (entity_code) DO UPDATE SET
                   total_loan_amount = entities.total_loan_amount + EXCLUDED.total_loan_amount,
                   updated_at = ?",
                [
                    $dto->entityCode,
                    $dto->totalLoans,
                    $now,
                    $now,
                    $now,
                ],
            );

            $this->repository->incrementPersisted($dto->importId);
        });

        if ($this->repository->tryCompleteAndClaim($dto->importId)) {
            $this->notificationSender->send(
                $this->buildCompletionEvent($dto->importId),
            );
        }
    }

    /**
     * Build a minimal ImportCompleted domain event from persisted import_log data.
     */
    private function buildCompletionEvent(string $importId): ImportCompleted
    {
        /** @var ImportLog|null $log */
        $log = ImportLog::find($importId);

        return new ImportCompleted(
            importId: $importId,
            filename: $log?->filename ?? '',
            totalDebtors: 0,
            totalEntities: $log?->valid_records ?? 0,
            durationMs: $log?->duration ?? 0,
            completedAt: new \DateTimeImmutable(),
        );
    }
}
