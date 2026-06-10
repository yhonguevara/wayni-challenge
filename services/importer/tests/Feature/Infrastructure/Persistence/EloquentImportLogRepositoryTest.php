<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence;

use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for EloquentImportLogRepository sentinel and ledger methods.
 *
 * Uses a real database (wayni_importer_test) and RefreshDatabase for isolation.
 * Tests cover: setExpectedAndPersisting, incrementPersisted, tryCompleteAndClaim,
 * and recordEventOnce — including idempotency and race-free claim semantics.
 */
class EloquentImportLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentImportLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentImportLogRepository(
            model: new ImportLog(),
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @var array<string, string> Map of logical test slot → UUID */
    private array $uuids = [];

    private function uuid(string $slot): string
    {
        if (!isset($this->uuids[$slot])) {
            $this->uuids[$slot] = \Str::uuid()->toString();
        }

        return $this->uuids[$slot];
    }

    private function createImportLog(string $importId, string $status = 'processing'): ImportLog
    {
        return ImportLog::create([
            'id'       => $importId,
            'filename' => 'test.txt',
            'status'   => $status,
        ]);
    }

    // -----------------------------------------------------------------------
    // setExpectedAndPersisting
    // -----------------------------------------------------------------------

    public function test_set_expected_and_persisting_sets_expected_records_and_status(): void
    {
        $importId = $this->uuid('001');
        $this->createImportLog($importId, 'processing');

        $this->repository->setExpectedAndPersisting($importId, 150);

        $log = ImportLog::find($importId);
        $this->assertSame(150, $log->expected_records);
        $this->assertSame('persisting', $log->status);
    }

    public function test_set_expected_and_persisting_does_not_update_when_status_is_completed(): void
    {
        $importId = $this->uuid('002');
        $this->createImportLog($importId, 'completed');

        $this->repository->setExpectedAndPersisting($importId, 50);

        $log = ImportLog::find($importId);
        // Status is completed — guard prevents overwrite
        $this->assertSame('completed', $log->status);
        $this->assertNull($log->expected_records);
    }

    // -----------------------------------------------------------------------
    // incrementPersisted
    // -----------------------------------------------------------------------

    public function test_increment_persisted_increments_counter(): void
    {
        $importId = $this->uuid('003');
        $this->createImportLog($importId);

        $this->repository->incrementPersisted($importId);

        $log = ImportLog::find($importId);
        $this->assertSame(1, $log->persisted_records);
    }

    public function test_increment_persisted_is_cumulative(): void
    {
        $importId = $this->uuid('004');
        $this->createImportLog($importId);

        $this->repository->incrementPersisted($importId);
        $this->repository->incrementPersisted($importId);
        $this->repository->incrementPersisted($importId);

        $log = ImportLog::find($importId);
        $this->assertSame(3, $log->persisted_records);
    }

    // -----------------------------------------------------------------------
    // tryCompleteAndClaim
    // -----------------------------------------------------------------------

    public function test_try_complete_and_claim_returns_true_when_counts_match(): void
    {
        $importId = $this->uuid('005');
        $log = $this->createImportLog($importId, 'persisting');
        $log->expected_records = 2;
        $log->persisted_records = 2;
        $log->save();

        $result = $this->repository->tryCompleteAndClaim($importId);

        $this->assertTrue($result);
        $refreshed = ImportLog::find($importId);
        $this->assertSame('completed', $refreshed->status);
        $this->assertNotNull($refreshed->finished_at);
    }

    public function test_try_complete_and_claim_returns_false_on_second_call(): void
    {
        $importId = $this->uuid('006');
        $log = $this->createImportLog($importId, 'persisting');
        $log->expected_records = 1;
        $log->persisted_records = 1;
        $log->save();

        $first  = $this->repository->tryCompleteAndClaim($importId);
        $second = $this->repository->tryCompleteAndClaim($importId);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_try_complete_and_claim_returns_false_when_expected_is_null(): void
    {
        $importId = $this->uuid('007');
        $log = $this->createImportLog($importId, 'persisting');
        $log->expected_records = null;
        $log->persisted_records = 5;
        $log->save();

        $result = $this->repository->tryCompleteAndClaim($importId);

        $this->assertFalse($result);
    }

    public function test_try_complete_and_claim_returns_false_when_persisted_less_than_expected(): void
    {
        $importId = $this->uuid('008');
        $log = $this->createImportLog($importId, 'persisting');
        $log->expected_records = 10;
        $log->persisted_records = 5;
        $log->save();

        $result = $this->repository->tryCompleteAndClaim($importId);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // recordEventOnce
    // -----------------------------------------------------------------------

    public function test_record_event_once_returns_true_on_first_call(): void
    {
        $importId = $this->uuid('009');
        $this->createImportLog($importId);

        $eventId = str_repeat('a', 64);
        $result  = $this->repository->recordEventOnce($importId, $eventId);

        $this->assertTrue($result);
    }

    public function test_record_event_once_returns_false_on_duplicate(): void
    {
        $importId = $this->uuid('010');
        $this->createImportLog($importId);

        $eventId = hash('sha256', 'some-unique-event-payload');

        $first  = $this->repository->recordEventOnce($importId, $eventId);
        $second = $this->repository->recordEventOnce($importId, $eventId);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_record_event_once_allows_same_event_id_for_different_imports(): void
    {
        $importIdA = $this->uuid('011a');
        $importIdB = $this->uuid('011b');
        $this->createImportLog($importIdA);
        $this->createImportLog($importIdB);

        $eventId = hash('sha256', 'shared-event-payload');

        $resultA = $this->repository->recordEventOnce($importIdA, $eventId);
        $resultB = $this->repository->recordEventOnce($importIdB, $eventId);

        $this->assertTrue($resultA);
        $this->assertTrue($resultB);
    }
}
