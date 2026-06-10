<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\ImportCompletedDTO;
use App\Application\Notification\NotificationSender;
use App\Application\Ports\ImportCompletedHandler;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;

/**
 * Handles ImportCompleted events arriving from SQS.
 *
 * This handler covers BOTH arrival orderings:
 *
 *   - ImportCompleted arrives LAST (after all DebtorProcessed/EntityProcessed):
 *     setExpectedAndPersisting finds persisted_records already == expected →
 *     tryCompleteAndClaim fires immediately → notification sent.
 *
 *   - ImportCompleted arrives FIRST (records still in-flight):
 *     setExpectedAndPersisting sets expected_records + status='persisting',
 *     but persisted < expected → tryCompleteAndClaim returns false → no notify.
 *     The last record handler will later claim and notify.
 *
 * The guarded UPDATE in tryCompleteAndClaim ensures exactly-once notification
 * regardless of ordering and concurrent workers.
 */
final class CompletionSentinelHandler implements ImportCompletedHandler
{
    public function __construct(
        private readonly ImportLogRepository $repository,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function handle(ImportCompletedDTO $dto): void
    {
        $total = $dto->totalDebtors + $dto->totalEntities;

        $this->repository->setExpectedAndPersisting($dto->importId, $total);

        if ($this->repository->tryCompleteAndClaim($dto->importId)) {
            $this->notificationSender->send(
                $this->buildCompletionEvent($dto),
            );
        }
    }

    /**
     * Build an ImportCompleted domain event from the DTO + persisted import_log data.
     *
     * The DTO carries totalDebtors and totalEntities directly.
     * filename and durationMs are sourced from the import_log row when available.
     */
    private function buildCompletionEvent(ImportCompletedDTO $dto): ImportCompleted
    {
        /** @var ImportLog|null $log */
        $log = ImportLog::find($dto->importId);

        return new ImportCompleted(
            importId: $dto->importId,
            filename: $log?->filename ?? '',
            totalDebtors: $dto->totalDebtors,
            totalEntities: $dto->totalEntities,
            durationMs: $log?->duration ?? 0,
            completedAt: new \DateTimeImmutable(),
        );
    }
}
