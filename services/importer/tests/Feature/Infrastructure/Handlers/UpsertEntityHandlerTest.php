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
 * Feature tests for UpsertEntityHandler.
 *
 * Mirrors UpsertDebtorHandlerTest patterns but for entity upserts.
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
    ): EntityProcessedDTO {
        return new EntityProcessedDTO(
            importId: $importId,
            entityCode: $entityCode,
            totalLoans: $loans,
        );
    }

    // -----------------------------------------------------------------------
    // Basic upsert
    // -----------------------------------------------------------------------

    public function test_handle_inserts_entity_row_on_first_delivery(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, 'BCO01', 150000.0);
        $this->handler->handle($dto);

        $this->assertDatabaseHas('entities', [
            'entity_code' => 'BCO01',
        ]);
    }

    public function test_handle_updates_existing_entity_row(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        Entity::create([
            'entity_code'       => 'BCO01',
            'total_loan_amount' => 50000.0,
        ]);

        $dto = $this->makeDto($importId, 'BCO01', 200000.0);
        $this->handler->handle($dto);

        $this->assertSame(1, Entity::count());
        $entity = Entity::first();
        $this->assertSame('200000.00', $entity->total_loan_amount);
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
    // Notification
    // -----------------------------------------------------------------------

    public function test_notification_fires_when_all_entities_are_persisted(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 2);

        $this->handler->handle($this->makeDto($importId, 'BCO01'));
        $this->assertCount(0, $this->sentNotifications);

        $this->handler->handle($this->makeDto($importId, 'BCO02'));
        $this->assertCount(1, $this->sentNotifications);
    }

    // -----------------------------------------------------------------------
    // Duplicate / idempotent redelivery
    // -----------------------------------------------------------------------

    public function test_duplicate_entity_event_does_not_double_count_or_notify(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId);
        $this->repository->setExpectedAndPersisting($importId, 1);

        $dto = $this->makeDto($importId, 'BCO01');

        $this->handler->handle($dto);
        $this->handler->handle($dto); // re-delivery

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);
        $this->assertCount(1, $this->sentNotifications);
    }
}
