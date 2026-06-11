<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Handlers;

use App\Application\DTOs\DebtorProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Handlers\UpsertDebtorHandler;
use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Models\Debtor;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for UpsertDebtorHandler — ADDITIVE streaming semantics.
 *
 * Each SQS event carries ONE raw line (situation + loans for that line).
 * The handler does an ADDITIVE upsert:
 *   total_loan_amount = existing + new (SUM over all delivered events)
 *   max_situation     = higher-severity wins (DB-side CASE expression)
 *
 * Event ID = sha256("{importId}|debtor|{lineNumber}") — per LINE, not per CUIT.
 * Same lineNumber re-delivered → idempotent (no double-count).
 */
class UpsertDebtorHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EloquentImportLogRepository $repository;
    private UpsertDebtorHandler $handler;

    /** @var ImportCompleted[] */
    private array $sentNotifications = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentImportLogRepository(model: new ImportLog());

        $notifier = $this->createNotificationSpy();

        $this->handler = new UpsertDebtorHandler(
            repository: $this->repository,
            notificationSender: $notifier,
        );
    }

    private function createNotificationSpy(): NotificationSender
    {
        $spy = new class ($this->sentNotifications) implements NotificationSender {
            /** @param ImportCompleted[] $log */
            public function __construct(private array &$log) {}

            public function send(ImportCompleted $event): void
            {
                $this->log[] = $event;
            }
        };

        return $spy;
    }

    private function createImportLog(string $importId, string $status = 'persisting'): ImportLog
    {
        return ImportLog::create([
            'id'       => $importId,
            'filename' => 'test.txt',
            'status'   => $status,
        ]);
    }

    private function makeDto(
        string $importId,
        string $cuit = '20111111111',
        string $situation = '03',
        float $loans = 5000.0,
        int $lineNumber = 1,
    ): DebtorProcessedDTO {
        return new DebtorProcessedDTO(
            importId: $importId,
            identificationNumber: $cuit,
            maxSituation: $situation,
            totalLoans: $loans,
            lineNumber: $lineNumber,
        );
    }

    // -----------------------------------------------------------------------
    // Basic insert
    // -----------------------------------------------------------------------

    public function test_handle_inserts_debtor_row_on_first_delivery(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, '20111111111', '03', 5000.0, lineNumber: 1);
        $this->handler->handle($dto);

        $this->assertDatabaseHas('debtors', [
            'identification_number' => '20111111111',
            'max_situation'         => '03',
        ]);

        $debtor = Debtor::where('identification_number', '20111111111')->first();
        $this->assertSame(5000.0, (float) $debtor->total_loan_amount);
    }

    // -----------------------------------------------------------------------
    // ADDITIVE loans: two events for same CUIT → SUM
    // -----------------------------------------------------------------------

    public function test_additive_upsert_sums_loans_across_events(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // Event 1: CUIT 20111111111 — line 1 — 1000
        $dto1 = $this->makeDto($importId, '20111111111', '01', 1000.0, lineNumber: 1);
        $this->handler->handle($dto1);

        // Event 2: same CUIT — line 5 — 9000
        $dto2 = $this->makeDto($importId, '20111111111', '01', 9000.0, lineNumber: 5);
        $this->handler->handle($dto2);

        $debtor = Debtor::where('identification_number', '20111111111')->first();
        $this->assertSame(10000.0, (float) $debtor->total_loan_amount);
    }

    // -----------------------------------------------------------------------
    // SEVERITY: max_situation picks the worse one regardless of arrival order
    // -----------------------------------------------------------------------

    /**
     * Critical test: situation '05' (Unrecoverable, severity 6) must win over
     * '23' (SpecialTreatment, severity 3) regardless of which arrives first.
     *
     * Severity map (from Situation value object):
     *   '01'→0, '11'→1, '21'→2, '23'→3, '03'→4, '04'→5, '05'→6
     */
    public function test_severity_case_picks_worse_situation_05_over_23(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // First event: situation '05' (Unrecoverable = severity 6)
        $dto1 = $this->makeDto($importId, '20111111111', '05', 1000.0, lineNumber: 10);
        $this->handler->handle($dto1);

        // Second event: situation '23' (SpecialTreatment = severity 3) — should NOT replace '05'
        $dto2 = $this->makeDto($importId, '20111111111', '23', 2000.0, lineNumber: 20);
        $this->handler->handle($dto2);

        $debtor = Debtor::where('identification_number', '20111111111')->first();
        $this->assertSame('05', $debtor->max_situation, "'05' must survive because severity(05)=6 > severity(23)=3");
        $this->assertSame(3000.0, (float) $debtor->total_loan_amount, "Loans must still be summed: 1000+2000=3000");
    }

    public function test_severity_case_picks_worse_situation_23_arrives_first_05_second(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // First event: situation '23' (SpecialTreatment = severity 3)
        $dto1 = $this->makeDto($importId, '20222222222', '23', 500.0, lineNumber: 3);
        $this->handler->handle($dto1);

        // Second event: situation '05' (Unrecoverable = severity 6) — must replace '23'
        $dto2 = $this->makeDto($importId, '20222222222', '05', 1500.0, lineNumber: 8);
        $this->handler->handle($dto2);

        $debtor = Debtor::where('identification_number', '20222222222')->first();
        $this->assertSame('05', $debtor->max_situation, "'05' must win because severity(05)=6 > severity(23)=3");
        $this->assertSame(2000.0, (float) $debtor->total_loan_amount);
    }

    public function test_severity_low_to_high_progression(): void
    {
        // '01'→'11'→'21'→'23'→'03'→'04'→'05' — each new one is worse
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $situations = [['01', 0], ['11', 1], ['21', 2], ['23', 3], ['03', 4], ['04', 5], ['05', 6]];
        $expectedLine = count($situations);
        $this->repository->setExpectedAndPersisting($importId, $expectedLine);

        $lineNumber = 0;
        foreach ($situations as [$situation, $expectedSeverity]) {
            $lineNumber++;
            $dto = $this->makeDto($importId, '20333333333', $situation, 100.0, lineNumber: $lineNumber);
            $this->handler->handle($dto);
        }

        $debtor = Debtor::where('identification_number', '20333333333')->first();
        $this->assertSame('05', $debtor->max_situation, "After all situations, '05' (severity 6) must be the max");
        $this->assertSame(700.0, (float) $debtor->total_loan_amount, "7 events × 100 = 700");
    }

    // -----------------------------------------------------------------------
    // Counter increments
    // -----------------------------------------------------------------------

    public function test_handle_increments_persisted_records(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);

        $dto = $this->makeDto($importId, lineNumber: 1);
        $this->handler->handle($dto);

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);
    }

    // -----------------------------------------------------------------------
    // Notification — exactly once (ImportCompleted arrives FIRST)
    // -----------------------------------------------------------------------

    public function test_notification_fires_on_last_record_when_expected_set_before_records(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        // expected=2, so both records must arrive before notify
        $this->repository->setExpectedAndPersisting($importId, 2);

        $dto1 = $this->makeDto($importId, '20111111111', lineNumber: 1);
        $dto2 = $this->makeDto($importId, '20222222222', lineNumber: 2);

        $this->handler->handle($dto1);
        $this->assertCount(0, $this->sentNotifications); // not yet

        $this->handler->handle($dto2);
        $this->assertCount(1, $this->sentNotifications); // fired on last
    }

    // -----------------------------------------------------------------------
    // Idempotent redelivery — same lineNumber = same eventId = no-op
    // -----------------------------------------------------------------------

    public function test_duplicate_event_same_line_number_does_not_double_increment_or_notify(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        // Same DTO with same lineNumber = same eventId
        $dto = $this->makeDto($importId, '20333333333', '03', 5000.0, lineNumber: 7);

        $this->handler->handle($dto);
        $this->handler->handle($dto); // re-delivery with same lineNumber

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);           // incremented only once
        $this->assertCount(1, $this->sentNotifications);         // notified only once

        // loans must not be double-added
        $debtor = Debtor::where('identification_number', '20333333333')->first();
        $this->assertSame(5000.0, (float) $debtor->total_loan_amount);
    }

    public function test_different_line_numbers_same_cuit_are_additive_not_idempotent(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // Same CUIT but different lineNumbers = different eventIds = both must count
        $dto1 = $this->makeDto($importId, '20444444444', '01', 3000.0, lineNumber: 10);
        $dto2 = $this->makeDto($importId, '20444444444', '01', 7000.0, lineNumber: 20);

        $this->handler->handle($dto1);
        $this->handler->handle($dto2);

        $debtor = Debtor::where('identification_number', '20444444444')->first();
        $this->assertSame(10000.0, (float) $debtor->total_loan_amount);

        $log = ImportLog::find($importId);
        $this->assertSame(2, $log->persisted_records);
    }

    // -----------------------------------------------------------------------
    // Cross-import isolation
    // -----------------------------------------------------------------------

    public function test_completing_import_a_does_not_trigger_notification_for_import_b(): void
    {
        $importIdA = \Str::uuid()->toString();
        $importIdB = \Str::uuid()->toString();

        $this->createImportLog($importIdA);
        $this->createImportLog($importIdB);

        $this->repository->setExpectedAndPersisting($importIdA, 1);
        $this->repository->setExpectedAndPersisting($importIdB, 1);

        $dtoA = $this->makeDto($importIdA, '20444444444', lineNumber: 1);
        $dtoB = $this->makeDto($importIdB, '20555555555', lineNumber: 1);

        $this->handler->handle($dtoA);
        $this->assertCount(1, $this->sentNotifications); // A done

        $this->handler->handle($dtoB);
        $this->assertCount(2, $this->sentNotifications); // B done separately

        // Each notification is scoped to its own import_id
        $this->assertSame($importIdA, $this->sentNotifications[0]->importId);
        $this->assertSame($importIdB, $this->sentNotifications[1]->importId);
    }
}
