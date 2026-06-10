<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\EntityProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\Entity;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Handles EntityProcessed events arriving from SQS.
 *
 * Within ONE DB transaction:
 *   1. Insert a ledger row via recordEventOnce — if duplicate, abort (idempotent).
 *   2. Upsert the entity row (RN-02: one consolidated row per entity code).
 *   3. Atomically increment persisted_records.
 *
 * After the transaction commits:
 *   4. Run the guarded tryCompleteAndClaim — fires notification exactly once.
 *
 * Event ID is a deterministic SHA-256 of importId + type + entityCode,
 * ensuring idempotency under SQS at-least-once delivery and RN-03 re-uploads.
 */
final class UpsertEntityHandler implements EntityEventHandler
{
    public function __construct(
        private readonly ImportLogRepository $repository,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function handle(EntityProcessedDTO $dto): void
    {
        $eventId = hash('sha256', $dto->importId . '|entity|' . $dto->entityCode);

        DB::transaction(function () use ($dto, $eventId): void {
            if (! $this->repository->recordEventOnce($dto->importId, $eventId)) {
                return; // duplicate delivery — no-op
            }

            Entity::updateOrCreate(
                ['entity_code' => $dto->entityCode],
                ['total_loan_amount' => $dto->totalLoans],
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
