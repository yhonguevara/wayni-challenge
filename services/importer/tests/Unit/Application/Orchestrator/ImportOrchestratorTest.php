<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Orchestrator;

use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for ImportOrchestrator — STREAMING-PURE design.
 *
 * New semantics:
 *   - TRUNCATES debtors and entities BEFORE publishing (latest file wins).
 *   - Streams parser->parse(); for each record builds ONE DebtorProcessed + ONE EntityProcessed
 *     (no aggregation in PHP memory — per-line events).
 *   - Publishes in batches (≤ 100 events per batch call).
 *   - publishImportCompleted with totalDebtors = totalEntities = lineCount.
 *   - No BcraDataTransformer involved.
 *   - Memory must be O(batch), not O(unique CUITs).
 */
class ImportOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrate_truncates_debtors_and_entities_before_publishing(): void
    {
        // Arrange — seed existing rows that must be wiped
        DB::table('debtors')->insert([
            'identification_number' => '20000000001',
            'max_situation'         => '01',
            'total_loan_amount'     => 999.0,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        DB::table('entities')->insert([
            'entity_code'       => '99999',
            'total_loan_amount' => 888.0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->method('create')->willReturn(new ImportLog());
        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 2);
        $importId     = (string) Str::uuid();

        // Act
        $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — stale rows must be gone (truncated before events start)
        $this->assertSame(0, DB::table('debtors')->where('identification_number', '20000000001')->count());
        $this->assertSame(0, DB::table('entities')->where('entity_code', '99999')->count());

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_publishes_two_events_per_line(): void
    {
        // Arrange — 3 valid lines → 6 events total (3 debtor + 3 entity)
        $publishedBatches = [];

        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->method('create')->willReturn(new ImportLog());

        $eventPublisher
            ->expects($this->atLeastOnce())
            ->method('publishBatch')
            ->willReturnCallback(function (array $batch) use (&$publishedBatches): void {
                $publishedBatches = array_merge($publishedBatches, $batch);
            });

        $eventPublisher->method('publishImportCompleted');

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 3);
        $importId     = (string) Str::uuid();

        // Act
        $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — exactly 2 events per line = 6 events total
        $this->assertCount(6, $publishedBatches, "3 lines × 2 events each = 6 total events");

        $debtorEvents = array_filter($publishedBatches, fn($e) => $e instanceof DebtorProcessed);
        $entityEvents = array_filter($publishedBatches, fn($e) => $e instanceof EntityProcessed);

        $this->assertCount(3, $debtorEvents, "One DebtorProcessed per line");
        $this->assertCount(3, $entityEvents, "One EntityProcessed per line");

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_events_carry_line_numbers(): void
    {
        // Arrange
        $publishedBatches = [];

        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->method('create')->willReturn(new ImportLog());

        $eventPublisher
            ->expects($this->atLeastOnce())
            ->method('publishBatch')
            ->willReturnCallback(function (array $batch) use (&$publishedBatches): void {
                $publishedBatches = array_merge($publishedBatches, $batch);
            });

        $eventPublisher->method('publishImportCompleted');

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 2);
        $importId     = (string) Str::uuid();

        // Act
        $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — every event has a positive lineNumber
        foreach ($publishedBatches as $event) {
            $this->assertGreaterThan(0, $event->lineNumber, "Every event must carry a positive lineNumber");
        }

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_publishes_import_completed_with_line_count(): void
    {
        // Arrange — 4 valid lines → totalDebtors=4 and totalEntities=4 (= lineCount each)
        $completedEvent = null;

        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->method('create')->willReturn(new ImportLog());

        $eventPublisher->method('publishBatch');
        $eventPublisher
            ->expects($this->once())
            ->method('publishImportCompleted')
            ->willReturnCallback(function (ImportCompleted $event) use (&$completedEvent): void {
                $completedEvent = $event;
            });

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 4);
        $importId     = (string) Str::uuid();

        // Act
        $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — totalDebtors = totalEntities = lineCount = 4
        $this->assertNotNull($completedEvent);
        $this->assertSame(4, $completedEvent->totalDebtors);
        $this->assertSame(4, $completedEvent->totalEntities);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_does_not_call_notification_sender(): void
    {
        // Arrange — orchestrator takes only EventPublisher + ImportLogRepository
        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->method('create')->willReturn(new ImportLog());

        // The orchestrator no longer has a NotificationSender parameter
        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 1);
        $importId     = (string) Str::uuid();

        // Act — must not throw, must not call any notification logic
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertInstanceOf(ImportLog::class, $result);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_sets_processing_status_not_completed(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $importLogRepository->method('find')->willReturn(null);

        $importLog = new ImportLog();
        $importLog->status = 'processing';
        $importLog->started_at = now();
        $importLogRepository->method('create')->willReturn($importLog);

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 1);
        $importId     = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — orchestrator must NOT set status=completed; sentinel owns that
        $this->assertNotSame('completed', $result->status);
        $this->assertNotNull($result->started_at);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_updates_import_log_with_failed_status_on_error(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch')->willThrowException(new \RuntimeException('SQS error'));
        $importLogRepository->method('find')->willReturn(null);

        $importLog = new ImportLog();
        $importLog->status = 'failed';
        $importLog->error_message = 'SQS error';
        $importLogRepository->method('create')->willReturn($importLog);
        $importLogRepository->expects($this->once())->method('updateStatus')
            ->with($this->anything(), 'failed', $this->arrayHasKey('error_message'));

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath  = $this->createFixtureFile(lineCount: 1);
        $importId     = (string) Str::uuid();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQS error');

        try {
            $orchestrator->orchestrate($fixturePath, $importId);
        } catch (\RuntimeException $e) {
            throw $e;
        } finally {
            @unlink($fixturePath);
        }
    }

    /**
     * Create a minimal valid BCRA fixture file with the given number of lines.
     * Each line has a unique identification number so all pass filtering.
     */
    private function createFixtureFile(int $lineCount = 2): string
    {
        $path  = tempnam(sys_get_temp_dir(), 'bcra_test_');
        $lines = [];

        for ($i = 0; $i < $lineCount; $i++) {
            // Fixed-width 171-char BCRA line:
            //   pos  1- 5: entity code       "00001"
            //   pos  6-11: info date         "202601"
            //   pos 12-13: id type           "11"  (CUIT — passes RN-03)
            //   pos 14-24: identification    "2034512345X" (last digit varies per line)
            //   pos 25-27: activity          "001"
            //   pos 28-29: situation         "01"  (valid — passes RN-04)
            //   pos 30-41: loans             "000000001500,"  12 chars
            //   pos 42-161: remaining amounts (12×10 = 120 chars of zeros + comma) + 1 flags
            //   pos 162-167: flags (6 × "0")
            //   pos 168-171: days overdue    "0000"
            $idNum      = str_pad((string) (20345123458 + $i), 11, '0', STR_PAD_LEFT);
            $amounts    = str_repeat('000000000000', 10); // 120 chars (10 fields of 12)
            $flags      = '000000';                       // 6 chars (fields 18-23)
            $daysOverdue = '0000';                        // 4 chars (field 24)

            // Construct the 171-char line:
            // field 1 (5) + field 2 (6) + field 3 (2) + field 4 (11) + field 5 (3)
            // + field 6 (2) + field 7 (12) + fields 8-17 (120) + fields 18-23 (6) + field 24 (4)
            // = 5+6+2+11+3+2+12+120+6+4 = 171
            $line = '00001'
                . '202601'
                . '11'
                . $idNum
                . '001'
                . '01'
                . '000000001500,'
                . $amounts
                . $flags
                . $daysOverdue;

            $lines[] = $line;
        }

        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }
}
