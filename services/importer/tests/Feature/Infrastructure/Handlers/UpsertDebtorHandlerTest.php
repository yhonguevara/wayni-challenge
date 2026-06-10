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
 * Feature tests for UpsertDebtorHandler.
 *
 * Covers: upsert semantics, idempotent event delivery, notification-exactly-once,
 * and cross-import isolation.
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
        $log = ImportLog::create([
            'id'       => $importId,
            'filename' => 'test.txt',
            'status'   => $status,
        ]);

        return $log;
    }

    private function makeDto(
        string $importId,
        string $cuit = '20111111111',
        string $situation = '03',
        float $loans = 5000.0,
    ): DebtorProcessedDTO {
        return new DebtorProcessedDTO(
            importId: $importId,
            identificationNumber: $cuit,
            maxSituation: $situation,
            totalLoans: $loans,
        );
    }

    // -----------------------------------------------------------------------
    // Basic upsert
    // -----------------------------------------------------------------------

    public function test_handle_inserts_debtor_row_on_first_delivery(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, '20111111111', '03', 5000.0);
        $this->handler->handle($dto);

        $this->assertDatabaseHas('debtors', [
            'identification_number' => '20111111111',
            'max_situation'         => '03',
        ]);
    }

    public function test_handle_updates_existing_debtor_row_on_re_upload(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        // Seed a pre-existing row
        Debtor::create([
            'identification_number' => '20111111111',
            'max_situation'         => '01',
            'total_loan_amount'     => 1000.0,
        ]);

        $dto = $this->makeDto($importId, '20111111111', '04', 9000.0);
        $this->handler->handle($dto);

        $this->assertSame(1, Debtor::count());
        $debtor = Debtor::first();
        $this->assertSame('04', $debtor->max_situation);
    }

    // -----------------------------------------------------------------------
    // Counter increments
    // -----------------------------------------------------------------------

    public function test_handle_increments_persisted_records(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);

        $dto = $this->makeDto($importId);
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

        $dto1 = $this->makeDto($importId, '20111111111');
        $dto2 = $this->makeDto($importId, '20222222222');

        $this->handler->handle($dto1);
        $this->assertCount(0, $this->sentNotifications); // not yet

        $this->handler->handle($dto2);
        $this->assertCount(1, $this->sentNotifications); // fired on last
    }

    // -----------------------------------------------------------------------
    // Duplicate / idempotent redelivery
    // -----------------------------------------------------------------------

    public function test_duplicate_event_does_not_double_increment_or_notify(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, '20333333333');

        $this->handler->handle($dto);
        $this->handler->handle($dto); // re-delivery

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);           // incremented only once
        $this->assertCount(1, $this->sentNotifications);         // notified only once
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

        $dtoA = $this->makeDto($importIdA, '20444444444');
        $dtoB = $this->makeDto($importIdB, '20555555555');

        $this->handler->handle($dtoA);
        $this->assertCount(1, $this->sentNotifications); // A done

        $this->handler->handle($dtoB);
        $this->assertCount(2, $this->sentNotifications); // B done separately

        // Each notification is scoped to its own import_id
        $this->assertSame($importIdA, $this->sentNotifications[0]->importId);
        $this->assertSame($importIdB, $this->sentNotifications[1]->importId);
    }
}
