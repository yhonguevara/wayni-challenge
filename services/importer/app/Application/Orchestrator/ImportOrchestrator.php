<?php

declare(strict_types=1);

namespace App\Application\Orchestrator;

use App\Application\Notification\NotificationSender;
use App\Application\Parser\BcraFileParser;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Persistence\Aggregator;
use App\Infrastructure\Persistence\StagingLoader;

/**
 * Coordinates the BCRA file processing pipeline — ELT design.
 *
 * Responsibilities:
 *   1. Find or create ImportLog; set status 'processing', started_at now.
 *   2. Call gcOrphans() to clean up stale staging tables from prior dead runs.
 *   3. Resume-aware load: if last_loaded_line > 0 AND staging table exists,
 *      StagingLoader resumes from that checkpoint; otherwise starts fresh.
 *      Rows stream O(1) from BcraFileParser into StagingLoader (never materialized).
 *   4. Aggregate: severity-correct GROUP BY INSERT into debtors + entities (TRUNCATE-first).
 *   5. Drop staging table.
 *   6. Set status 'completed', finished_at, total_records, valid_records, duration.
 *   7. Build ImportCompleted domain event; call notificationSender->send() directly.
 *
 * Error path:
 *   - Set status 'failed', error_message, finished_at.
 *   - Staging table is RETAINED (for resume on retry).
 *   - Re-throw the exception.
 *
 * Status path: processing → completed | failed.
 * There is NO 'publishing' or 'persisting' status in the ELT design.
 */
final class ImportOrchestrator
{
    public function __construct(
        private readonly StagingLoader $stagingLoader,
        private readonly Aggregator $aggregator,
        private readonly ImportLogRepository $importLogRepository,
        private readonly NotificationSender $notificationSender,
    ) {}

    /**
     * Execute the full ELT import pipeline.
     *
     * @throws \Throwable on processing failure (staging is retained for resume)
     */
    public function orchestrate(string $filePath, string $importId): void
    {
        $startTime = microtime(true);

        // 1. Find or create ImportLog; set status 'processing'.
        $importLog = $this->importLogRepository->find($importId);

        if ($importLog === null) {
            $this->importLogRepository->create([
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
            // 2. GC: clean up stale staging tables from prior dead runs.
            $this->stagingLoader->gcOrphans();

            // 3. Load: stream parser rows into StagingLoader (O(1) memory).
            //    StagingLoader internally handles resume vs. fresh-start based on
            //    last_loaded_line from import_logs. The orchestrator just passes the
            //    full row stream; the loader skips already-loaded lines itself.
            $parser = new BcraFileParser($filePath);
            $this->stagingLoader->load($importId, $parser->parse());

            // 4. Aggregate: severity-correct GROUP BY INSERT from staging to targets.
            $this->aggregator->aggregate($importId);

            // 5. Drop staging table (success path only).
            $this->stagingLoader->dropStaging($importId);

            // 6. Mark as 'completed'.
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $lineCount = $this->fetchLineCount($importId);

            $this->importLogRepository->updateStatus($importId, 'completed', [
                'finished_at'   => now(),
                'total_records' => $lineCount,
                'valid_records' => $lineCount,
                'duration'      => $durationMs,
            ]);

            // 7. Send completion notification directly (sequential — no consume-path).
            $importCompleted = new ImportCompleted(
                importId: $importId,
                filename: basename($filePath),
                totalDebtors: $lineCount,
                totalEntities: $lineCount,
                durationMs: $durationMs,
                completedAt: new \DateTimeImmutable(),
            );

            $this->notificationSender->send($importCompleted);
        } catch (\Throwable $e) {
            // Error path: mark failed, RETAIN staging for resume, re-throw.
            $this->importLogRepository->updateStatus($importId, 'failed', [
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Read the last_loaded_line from import_logs as a proxy for total rows loaded.
     *
     * After a full successful load, last_loaded_line equals the number of valid
     * records written to staging (the loader advances it per chunk to the last
     * line number emitted by the parser).
     */
    private function fetchLineCount(string $importId): int
    {
        $row = \Illuminate\Support\Facades\DB::table('import_logs')
            ->where('id', $importId)
            ->value('last_loaded_line');

        return (int) ($row ?? 0);
    }
}
