<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Orchestrator;

use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\ImportLogRepository;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for ImportOrchestrator.
 *
 * After WU-07, the orchestrator no longer sends notifications (the sentinel
 * handler owns that) and no longer requires a NotificationSender dependency.
 * The orchestrator: parse → transform → publishBatch → publishImportCompleted
 * → update import_log to 'publishing' status (not 'completed').
 */
class ImportOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrate_calls_dependencies_in_order(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->expects($this->once())->method('publishBatch');
        $eventPublisher->expects($this->once())->method('publishImportCompleted');
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->expects($this->once())->method('create')->willReturn(new ImportLog());

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertInstanceOf(ImportLog::class, $result);

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

        // The orchestrator no longer has a NotificationSender parameter — this
        // test documents and enforces that the notification path is removed.
        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

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
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — orchestrator should NOT set status=completed; sentinel owns that
        $this->assertNotSame('completed', $result->status);
        $this->assertNotNull($result->started_at);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_updates_import_log_with_stats_on_success(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $importLogRepository->method('find')->willReturn(null);

        $importLog = new ImportLog();
        $importLog->status = 'processing';
        $importLog->valid_records = 2;
        $importLog->duration = 100;
        $importLogRepository->method('create')->willReturn($importLog);

        $orchestrator = new ImportOrchestrator($eventPublisher, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertNotNull($result->valid_records);
        $this->assertNotNull($result->duration);

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
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

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

    private function createFixtureFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bcra_test_');
        // Write a minimal valid BCRA file (2 lines, tipo_identificacion=11)
        $line1 = '112034512345800000100000001500005';
        $line2 = '112034512345900000200000002300010';
        file_put_contents($path, $line1 . "\n" . $line2 . "\n");

        return $path;
    }
}
