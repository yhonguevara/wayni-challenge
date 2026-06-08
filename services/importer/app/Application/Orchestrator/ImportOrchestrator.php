<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Application\Notification\NotificationSender;
use App\Application\Parser\BcraFileParser;
use App\Application\Ports\EventPublisher;
use App\Application\Transformer\BcraDataTransformer;
use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\DomainEvent;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Support\Str;

/**
 * Coordinates the BCRA file processing pipeline.
 *
 * Orchestrates: parse → transform → publish events → notify → update log.
 * This is a thin Application-layer coordinator — no business logic, only sequencing.
 */
final class ImportOrchestrator
{
    public function __construct(
        private readonly EventPublisher $eventPublisher,
        private readonly NotificationSender $notificationSender,
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
        $importLog = ImportLog::firstOrCreate(
            ['id' => $importId],
            [
                'filename' => basename($filePath),
                'status' => 'processing',
                'started_at' => now(),
            ]
        );
        $importLog->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            // 2. Parse file
            $parser = new BcraFileParser($filePath);
            $records = $parser->parse();

            // 3. Transform records
            $transformer = new BcraDataTransformer($records);
            $result = $transformer->transform();

            $debtors = $result['debtors'];
            $entities = $result['entities'];

            // 4. Build and publish domain events
            $events = $this->buildEvents($debtors, $entities, $importId);
            $this->eventPublisher->publishBatch($events);

            // 5. Create and publish ImportCompleted event
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $importCompleted = new ImportCompleted(
                filename: basename($filePath),
                totalDebtors: $debtors->count(),
                totalEntities: $entities->count(),
                durationMs: $durationMs,
                completedAt: new \DateTimeImmutable(),
            );

            $this->eventPublisher->publishImportCompleted($importCompleted);

            // 6. Send notification
            $this->notificationSender->send($importCompleted);

            // 7. Update ImportLog (status: completed)
            $importLog->update([
                'status' => 'completed',
                'finished_at' => now(),
                'total_lines' => $debtors->count() + $entities->count(),
                'total_debtors' => $debtors->count(),
                'total_entities' => $entities->count(),
                'duration_ms' => $durationMs,
            ]);

            return $importLog;
        } catch (\Throwable $e) {
            // Update ImportLog (status: failed, error_message)
            $importLog->update([
                'status' => 'failed',
                'finished_at' => now(),
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
        $now = new \DateTimeImmutable();

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
