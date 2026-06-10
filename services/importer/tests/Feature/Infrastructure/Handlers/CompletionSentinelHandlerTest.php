<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Handlers;

use App\Application\DTOs\ImportCompletedDTO;
use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Handlers\CompletionSentinelHandler;
use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for CompletionSentinelHandler.
 *
 * Covers: ImportCompleted arrives LAST (expected set after all records done);
 * ImportCompleted arrives FIRST (expected set before records — notification
 * deferred to the last record handler); idempotency; cross-import isolation.
 */
class CompletionSentinelHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EloquentImportLogRepository $repository;
    private CompletionSentinelHandler $handler;

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

        $this->handler = new CompletionSentinelHandler(
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
        int $totalDebtors = 2,
        int $totalEntities = 1,
    ): ImportCompletedDTO {
        return new ImportCompletedDTO(
            importId: $importId,
            totalDebtors: $totalDebtors,
            totalEntities: $totalEntities,
        );
    }

    // -----------------------------------------------------------------------
    // ImportCompleted arrives LAST — all records already persisted
    // -----------------------------------------------------------------------

    public function test_notification_fires_when_import_completed_arrives_last(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId, 'processing');

        // Simulate: all 3 records were already persisted before ImportCompleted arrived
        $log = ImportLog::find($importId);
        $log->persisted_records = 3;
        $log->save();

        $dto = $this->makeDto($importId, totalDebtors: 2, totalEntities: 1); // 3 total

        $this->handler->handle($dto);

        // setExpectedAndPersisting sets expected=3 + persisting → guard fires → notify
        $this->assertCount(1, $this->sentNotifications);
        $this->assertSame($importId, $this->sentNotifications[0]->importId);
    }

    // -----------------------------------------------------------------------
    // ImportCompleted arrives FIRST — records not yet persisted
    // -----------------------------------------------------------------------

    public function test_notification_does_not_fire_when_import_completed_arrives_first(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId, 'processing');

        // No records persisted yet
        $dto = $this->makeDto($importId, totalDebtors: 2, totalEntities: 1); // 3 total

        $this->handler->handle($dto);

        // expected=3 set, persisted=0 → guard fails → no notification
        $this->assertCount(0, $this->sentNotifications);

        $log = ImportLog::find($importId);
        $this->assertSame('persisting', $log->status);
        $this->assertSame(3, $log->expected_records);
    }

    // -----------------------------------------------------------------------
    // Idempotent re-delivery of ImportCompleted
    // -----------------------------------------------------------------------

    public function test_duplicate_import_completed_does_not_double_notify(): void
    {
        $importId = \Str::uuid()->toString();
        $this->createImportLog($importId, 'processing');

        $log = ImportLog::find($importId);
        $log->persisted_records = 3;
        $log->save();

        $dto = $this->makeDto($importId, totalDebtors: 2, totalEntities: 1);

        $this->handler->handle($dto);
        $this->handler->handle($dto); // re-delivery

        // Second handle: setExpectedAndPersisting guard fires (status='completed' → no update),
        // tryCompleteAndClaim guard fires (status≠'persisting' → false)
        $this->assertCount(1, $this->sentNotifications);
    }

    // -----------------------------------------------------------------------
    // Cross-import isolation
    // -----------------------------------------------------------------------

    public function test_completing_one_import_does_not_affect_another(): void
    {
        $importIdA = \Str::uuid()->toString();
        $importIdB = \Str::uuid()->toString();

        $this->createImportLog($importIdA, 'processing');
        $this->createImportLog($importIdB, 'processing');

        // Import A fully persisted
        $logA = ImportLog::find($importIdA);
        $logA->persisted_records = 2;
        $logA->save();

        // Import B not yet persisted
        $dtoA = $this->makeDto($importIdA, totalDebtors: 2, totalEntities: 0);
        $dtoB = $this->makeDto($importIdB, totalDebtors: 5, totalEntities: 3);

        $this->handler->handle($dtoA);
        $this->assertCount(1, $this->sentNotifications); // A done

        $this->handler->handle($dtoB);
        $this->assertCount(1, $this->sentNotifications); // B not done (0 of 8 persisted)

        $logB = ImportLog::find($importIdB);
        $this->assertSame('persisting', $logB->status);
        $this->assertSame(8, $logB->expected_records);
    }
}
