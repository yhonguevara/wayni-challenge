<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\DebtorProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

/**
 * Handles DebtorProcessed events arriving from SQS.
 *
 * STREAMING-PURE design: each event carries ONE raw file line's data.
 * The handler performs an ADDITIVE upsert so the database aggregates:
 *   total_loan_amount = SUM of all delivered loan amounts
 *   max_situation     = highest-severity situation seen so far
 *
 * Severity order (from Situation value object):
 *   '01'→0, '11'→1, '21'→2, '23'→3, '03'→4, '04'→5, '05'→6
 *
 * Within ONE DB transaction:
 *   1. Insert a ledger row via recordEventOnce — if duplicate, abort (idempotent).
 *   2. ADDITIVE upsert the debtor row (ON CONFLICT DO UPDATE with SUM/MAX-severity).
 *   3. Atomically increment persisted_records.
 *
 * After the transaction commits:
 *   4. Run the guarded tryCompleteAndClaim — fires notification exactly once.
 *
 * Event ID = sha256("{importId}|debtor|{lineNumber}") — per LINE, not per CUIT,
 * because many events share the same CUIT in the streaming design. Same lineNumber
 * re-delivered → idempotent (no double-count, no double-add).
 */
final class UpsertDebtorHandler implements DebtorEventHandler
{
    /**
     * Maps situation code to its severity level for the DB-side CASE expression.
     * Must match App\Domain\ValueObjects\Situation::severity().
     *
     * @var array<string, int>
     */
    private const SEVERITY = [
        '01' => 0,
        '11' => 1,
        '21' => 2,
        '23' => 3,
        '03' => 4,
        '04' => 5,
        '05' => 6,
    ];

    public function __construct(
        private readonly ImportLogRepository $repository,
        private readonly NotificationSender $notificationSender,
    ) {}

    public function handle(DebtorProcessedDTO $dto): void
    {
        // Per-line event ID — same CUIT in different lines must NOT share the same ID
        $eventId = hash('sha256', $dto->importId . '|debtor|' . $dto->lineNumber);

        DB::transaction(function () use ($dto, $eventId): void {
            if (! $this->repository->recordEventOnce($dto->importId, $eventId)) {
                return; // duplicate delivery — idempotent no-op
            }

            // ADDITIVE upsert: SUM loans, MAX severity-based situation
            $newSeverity = self::SEVERITY[$dto->maxSituation] ?? 0;
            $now = now()->format('Y-m-d H:i:s');

            // Build severity CASE for existing row
            $existingSeverityCase = $this->buildSeverityCaseExpr('debtors.max_situation');
            // Build severity CASE for the incoming value
            $newSeverityCase = (string) $newSeverity;

            DB::statement(
                "INSERT INTO debtors (identification_number, max_situation, total_loan_amount, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT (identification_number) DO UPDATE SET
                   total_loan_amount = debtors.total_loan_amount + EXCLUDED.total_loan_amount,
                   max_situation = CASE
                     WHEN ({$existingSeverityCase}) >= {$newSeverityCase}
                     THEN debtors.max_situation
                     ELSE EXCLUDED.max_situation
                   END,
                   updated_at = ?",
                [
                    $dto->identificationNumber,
                    $dto->maxSituation,
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
     * Build a CASE expression that maps a situation column to its numeric severity.
     *
     * Produces SQL like:
     *   CASE debtors.max_situation WHEN '01' THEN 0 WHEN '11' THEN 1 ... ELSE 0 END
     */
    private function buildSeverityCaseExpr(string $column): string
    {
        $cases = '';

        foreach (self::SEVERITY as $code => $severity) {
            $cases .= " WHEN '{$code}' THEN {$severity}";
        }

        return "CASE {$column}{$cases} ELSE 0 END";
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
            totalDebtors: $log?->valid_records ?? 0,
            totalEntities: 0,
            durationMs: $log?->duration ?? 0,
            completedAt: new \DateTimeImmutable(),
        );
    }
}
