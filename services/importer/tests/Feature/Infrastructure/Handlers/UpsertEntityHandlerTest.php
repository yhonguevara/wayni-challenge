<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Handlers;

use App\Application\DTOs\EntityProcessedDTO;
use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Handlers\UpsertEntityHandler;
use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Models\Entity;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for UpsertEntityHandler — ADDITIVE streaming semantics.
 *
 * Each SQS event carries ONE raw line loan amount for a given entity code.
 * The handler performs an ADDITIVE upsert:
 *   total_loan_amount = existing + new (SUM over all delivered events)
 *
 * Event ID = sha256("{importId}|entity|{lineNumber}") — per LINE, not per entity code.
 */
class UpsertEntityHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EloquentImportLogRepository $repository;
    private UpsertEntityHandler $handler;

    /** @var ImportCompleted[] */
    private array $sentNotifications = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentImportLogRepository(model: new ImportLog());

        $notifier = new class ($this->sentNotifications) implements NotificationSender {
            /** @param ImportCompleted[] $log */
            public function __construct(private array &$log) {}

            public function send(ImportCompleted $event): void
            {
                $this->log[] = $event;
            }
        };

        $this->handler = new UpsertEntityHandler(
            repository: $this->repository,
            notificationSender: $notifier,
        );
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
        string $entityCode = 'BCO01',
        float $loans = 150000.0,
        int $lineNumber = 1,
    ): EntityProcessedDTO {
        return new EntityProcessedDTO(
            importId: $importId,
            entityCode: $entityCode,
            totalLoans: $loans,
            lineNumber: $lineNumber,
        );
    }

    // -----------------------------------------------------------------------
    // Basic insert
    // -----------------------------------------------------------------------

    public function test_handle_inserts_entity_row_on_first_delivery(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, 'BCO01', 150000.0, lineNumber: 1);
        $this->handler->handle($dto);

        $this->assertDatabaseHas('entities', [
            'entity_code' => 'BCO01',
        ]);

        $entity = Entity::where('entity_code', 'BCO01')->first();
        $this->assertSame(150000.0, (float) $entity->total_loan_amount);
    }

    // -----------------------------------------------------------------------
    // ADDITIVE loans: existing 50000 + new 200000 = 250000
    // -----------------------------------------------------------------------

    public function test_additive_upsert_sums_loans_across_events(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // Event 1: entity BCO01 — line 1 — 50000
        $dto1 = $this->makeDto($importId, 'BCO01', 50000.0, lineNumber: 1);
        $this->handler->handle($dto1);

        // Event 2: same entity — line 2 — 200000
        $dto2 = $this->makeDto($importId, 'BCO01', 200000.0, lineNumber: 2);
        $this->handler->handle($dto2);

        $entity = Entity::where('entity_code', 'BCO01')->first();
        $this->assertSame(250000.0, (float) $entity->total_loan_amount, "50000 + 200000 = 250000");
    }

    public function test_additive_upsert_multiple_events_for_same_entity(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 3);

        $this->handler->handle($this->makeDto($importId, 'BCO02', 10000.0, lineNumber: 5));
        $this->handler->handle($this->makeDto($importId, 'BCO02', 20000.0, lineNumber: 10));
        $this->handler->handle($this->makeDto($importId, 'BCO02', 30000.0, lineNumber: 15));

        $entity = Entity::where('entity_code', 'BCO02')->first();
        $this->assertSame(60000.0, (float) $entity->total_loan_amount, "10000 + 20000 + 30000 = 60000");
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
    // Notification
    // -----------------------------------------------------------------------

    public function test_notification_fires_when_all_entities_are_persisted(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        $this->handler->handle($this->makeDto($importId, 'BCO01', lineNumber: 1));
        $this->assertCount(0, $this->sentNotifications);

        $this->handler->handle($this->makeDto($importId, 'BCO02', lineNumber: 2));
        $this->assertCount(1, $this->sentNotifications);
    }

    // -----------------------------------------------------------------------
    // Duplicate / idempotent redelivery — same lineNumber = same eventId = no-op
    // -----------------------------------------------------------------------

    public function test_duplicate_entity_event_same_line_number_does_not_double_count_or_notify(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, 'BCO01', 150000.0, lineNumber: 3);

        $this->handler->handle($dto);
        $this->handler->handle($dto); // re-delivery with same lineNumber

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);
        $this->assertCount(1, $this->sentNotifications);

        // loans must not be double-added
        $entity = Entity::where('entity_code', 'BCO01')->first();
        $this->assertSame(150000.0, (float) $entity->total_loan_amount);
    }

    public function test_different_line_numbers_same_entity_are_additive_not_idempotent(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        // Same entity code but different lineNumbers = different eventIds = both must count
        $dto1 = $this->makeDto($importId, 'BCO03', 100000.0, lineNumber: 7);
        $dto2 = $this->makeDto($importId, 'BCO03', 50000.0, lineNumber: 14);

        $this->handler->handle($dto1);
        $this->handler->handle($dto2);

        $entity = Entity::where('entity_code', 'BCO03')->first();
        $this->assertSame(150000.0, (float) $entity->total_loan_amount);

        $log = ImportLog::find($importId);
        $this->assertSame(2, $log->persisted_records);
    }
}
