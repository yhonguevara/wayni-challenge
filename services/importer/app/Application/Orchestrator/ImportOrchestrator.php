<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Application\Parser\BcraFileParser;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates the BCRA file processing pipeline — STREAMING-PURE design.
 *
 * Responsibilities:
 *   1. Set status 'processing'.
 *   2. TRUNCATE debtors and entities so re-upload replaces, not accumulates.
 *   3. Stream parser->parse(); for each record build ONE DebtorProcessed +
 *      ONE EntityProcessed event (NO in-memory aggregation).
 *   4. Publish events in batches of BATCH_SIZE.
 *   5. Publish ImportCompleted with totalDebtors = totalEntities = lineCount.
 *   6. Set status 'publishing' (sentinel owns 'completed').
 *
 * Memory is O(batch), not O(unique CUITs) — safe for the 5.6 GB / 34 M line file.
 *
 * Notification is NOT sent here. The importer's ConsumeEventsCommand drives
 * CompletionSentinelHandler which fires the notification exactly once after all
 * records are persisted. This ensures "notified" means "persisted".
 */
final class ImportOrchestrator
{
    /** Number of events accumulated before calling publishBatch. */
    private const BATCH_SIZE = 100;

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
            // 2. TRUNCATE debtors and entities — "latest file wins" semantics.
            //    Re-upload replaces the previous state instead of accumulating.
            DB::table('debtors')->truncate();
            DB::table('entities')->truncate();

            // 3. Stream parser and publish per-line events
            $parser    = new BcraFileParser($filePath);
            $batch     = [];
            $lineCount = 0;
            $now       = new \DateTimeImmutable();

            foreach ($parser->parse() as $record) {
                $lineCount++;

                $batch[] = new DebtorProcessed(
                    identificationNumber: $record->identificationNumber,
                    maxSituation: $record->situation,
                    totalLoans: $record->loans,
                    importId: $importId,
                    processedAt: $now,
                    lineNumber: $record->lineNumber,
                );

                $batch[] = new EntityProcessed(
                    entityCode: $record->entityCode,
                    totalLoans: $record->loans,
                    importId: $importId,
                    processedAt: $now,
                    lineNumber: $record->lineNumber,
                );

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->eventPublisher->publishBatch($batch);
                    $batch = [];
                }
            }

            // Flush remaining events
            if ($batch !== []) {
                $this->eventPublisher->publishBatch($batch);
            }

            // 4. Publish ImportCompleted.
            //    totalDebtors = totalEntities = lineCount so the sentinel's
            //    $total = totalDebtors + totalEntities = 2 * lineCount, which
            //    matches exactly 2 events published per line.
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $importCompleted = new ImportCompleted(
                importId: $importId,
                filename: basename($filePath),
                totalDebtors: $lineCount,
                totalEntities: $lineCount,
                durationMs: $durationMs,
                completedAt: new \DateTimeImmutable(),
            );

            $this->eventPublisher->publishImportCompleted($importCompleted);

            // 5. Update ImportLog — mark as 'publishing'.
            //    'completed' is owned by the sentinel handler after all records are persisted.
            $this->importLogRepository->updateStatus($importId, 'publishing', [
                'total_records'   => $lineCount * 2,
                'valid_records'   => $lineCount,
                'invalid_records' => 0,
                'duration'        => $durationMs,
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
}
