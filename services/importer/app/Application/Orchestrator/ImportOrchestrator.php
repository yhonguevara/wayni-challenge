<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Application\Parser\BcraFileParser;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\ImportLogRepository;
use App\Application\Transformer\BcraDataTransformer;
use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\DomainEvent;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;

/**
 * Coordinates the BCRA file processing pipeline.
 *
 * Responsibilities: parse → transform → publish DebtorProcessed/EntityProcessed
 * batch → publish ImportCompleted → update import_log status to 'publishing'.
 *
 * Notification is NO LONGER sent here. The importer's ConsumeEventsCommand
 * drives the CompletionSentinelHandler which fires the notification exactly once
 * after all records are persisted. This ensures "notified" means "persisted".
 */
final class ImportOrchestrator
{
    public function __construct(
        private readonly EventPublisher $eventPublisher,
        private readonly ImportLogRepository $importLogRepository,
    ) {}

    /**
     * Execute the full import pipeline.
     *
     * @throws \Throwable on processing failure
     */
    public function orchestrate(string $filePath, string $importId): ImportLog
    {
        $startTime = microtime(true);

        // 1. Create/update ImportLog (status: processing, started_at: now)
        $importLog = $this->importLogRepository->find($importId);

        if ($importLog === null) {
            $importLog = $this->importLogRepository->create([
                'id'         => $importId,
                'filename'   => basename($filePath),
                'status'     => 'processing',
                'started_at' => now(),
            ]);
        } else {
            $this->importLogRepository->updateStatus($importId, 'processing', [
                'started_at' => now(),
            ]);
        }

        try {
            // 2. Parse file
            $parser = new BcraFileParser($filePath);
            $records = $parser->parse();

            // 3. Transform records
            $transformer = new BcraDataTransformer($records);
            $result = $transformer->transform();

            $debtors  = $result['debtors'];
            $entities = $result['entities'];

            // 4. Build and publish domain events
            $events = $this->buildEvents($debtors, $entities, $importId);
            $this->eventPublisher->publishBatch($events);

            // 5. Build and publish ImportCompleted event
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $importCompleted = new ImportCompleted(
                importId: $importId,
                filename: basename($filePath),
                totalDebtors: $debtors->count(),
                totalEntities: $entities->count(),
                durationMs: $durationMs,
                completedAt: new \DateTimeImmutable(),
            );

            $this->eventPublisher->publishImportCompleted($importCompleted);

            // 6. Update ImportLog — record stats and mark as 'publishing'.
            //    The 'completed' status is owned by the sentinel handler after
            //    all records are persisted. Do NOT set status='completed' here.
            $this->importLogRepository->updateStatus($importId, 'publishing', [
                'total_records'  => $debtors->count() + $entities->count(),
                'valid_records'  => $debtors->count(),
                'invalid_records' => 0,
                'duration'       => $durationMs,
            ]);

            return $importLog;
        } catch (\Throwable $e) {
            $this->importLogRepository->updateStatus($importId, 'failed', [
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build domain events from aggregated records.
     *
     * @return array<int, DomainEvent>
     */
    private function buildEvents(
        \Illuminate\Support\Collection $debtors,
        \Illuminate\Support\Collection $entities,
        string $importId,
    ): array {
        $events = [];
        $now    = new \DateTimeImmutable();

        foreach ($debtors as $debtor) {
            $events[] = new DebtorProcessed(
                identificationNumber: $debtor->identificationNumber->value(),
                maxSituation: $debtor->maxSituation->value,
                totalLoans: $debtor->totalLoans->toFloat(),
                importId: $importId,
                processedAt: $now,
            );
        }

        foreach ($entities as $entity) {
            $events[] = new EntityProcessed(
                entityCode: $entity->entityCode,
                totalLoans: $entity->totalLoans->toFloat(),
                importId: $importId,
                processedAt: $now,
            );
        }

        return $events;
    }
}
