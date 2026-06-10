<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\DebtorProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\Debtor;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Handles DebtorProcessed events arriving from SQS.
 *
 * Within ONE DB transaction:
 *   1. Insert a ledger row via recordEventOnce — if duplicate, abort (idempotent).
 *   2. Upsert the debtor row (RN-01: one consolidated row per CUIT).
 *   3. Atomically increment persisted_records.
 *
 * After the transaction commits:
 *   4. Run the guarded tryCompleteAndClaim — fires notification exactly once.
 *
 * Event ID is a deterministic SHA-256 of importId + type + identificationNumber,
 * ensuring idempotency under SQS at-least-once delivery and RN-03 re-uploads.
 */
final class UpsertDebtorHandler implements DebtorEventHandler
{
    public function __construct(
        private readonly ImportLogRepository $repository,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function handle(DebtorProcessedDTO $dto): void
    {
        $eventId = hash('sha256', $dto->importId . '|debtor|' . $dto->identificationNumber);

        DB::transaction(function () use ($dto, $eventId): void {
            if (! $this->repository->recordEventOnce($dto->importId, $eventId)) {
                return; // duplicate delivery — no-op
            }

            Debtor::updateOrCreate(
                ['identification_number' => $dto->identificationNumber],
                [
                    'max_situation'     => $dto->maxSituation,
                    'total_loan_amount' => $dto->totalLoans,
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
     *
     * filename, totalDebtors, totalEntities, and durationMs are sourced from the
     * import_log row when available; sensible defaults are used otherwise.
     */
    private function buildCompletionEvent(string $importId): ImportCompleted
    {
        /** @var ImportLog|null $log */
        $log = ImportLog::find($importId);

        return new ImportCompleted(
            importId: $importId,
            filename: $log?->filename ?? '',
            totalDebtors: $log?->valid_records ?? 0,
            totalEntities: 0,
            durationMs: $log?->duration ?? 0,
            completedAt: new \DateTimeImmutable(),
        );
    }
}
