<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Orchestrator;

use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrate_calls_dependencies_in_order(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $notificationSender = $this->createMock(NotificationSender::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->expects($this->once())->method('publishBatch');
        $eventPublisher->expects($this->once())->method('publishImportCompleted');
        $notificationSender->expects($this->once())->method('send');
        $importLogRepository->method('find')->willReturn(null);
        $importLogRepository->expects($this->once())->method('create')->willReturn(new ImportLog());

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertInstanceOf(ImportLog::class, $result);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_creates_import_log_with_completed_status(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $notificationSender = $this->createMock(NotificationSender::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $notificationSender->method('send');
        $importLogRepository->method('find')->willReturn(null);
        $importLog = new ImportLog();
        $importLog->status = 'completed';
        $importLog->started_at = now();
        $importLog->finished_at = now();
        $importLogRepository->method('create')->willReturn($importLog);

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertSame('completed', $result->status);
        $this->assertNotNull($result->started_at);
        $this->assertNotNull($result->finished_at);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_updates_import_log_with_stats_on_success(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $notificationSender = $this->createMock(NotificationSender::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $notificationSender->method('send');
        $importLogRepository->method('find')->willReturn(null);
        $importLog = new ImportLog();
        $importLog->status = 'completed';
        $importLog->valid_records = 2;
        $importLog->duration = 100;
        $importLogRepository->method('create')->willReturn($importLog);

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender, $importLogRepository);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertSame('completed', $result->status);
        $this->assertNotNull($result->valid_records);
        $this->assertNotNull($result->duration);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_updates_import_log_with_failed_status_on_error(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $notificationSender = $this->createMock(NotificationSender::class);
        $importLogRepository = $this->createMock(ImportLogRepository::class);

        $eventPublisher->method('publishBatch')->willThrowException(new \RuntimeException('SQS error'));
        $importLogRepository->method('find')->willReturn(null);
        $importLog = new ImportLog();
        $importLog->status = 'failed';
        $importLog->error_message = 'SQS error';
        $importLogRepository->method('create')->willReturn($importLog);
        $importLogRepository->expects($this->once())->method('updateStatus')
            ->with($this->anything(), 'failed', $this->arrayHasKey('error_message'));

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender, $importLogRepository);
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
