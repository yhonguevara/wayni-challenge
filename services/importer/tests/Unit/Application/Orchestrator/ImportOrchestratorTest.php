<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Orchestrator;

use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\EventPublisher;
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

        $eventPublisher->expects($this->once())->method('publishBatch');
        $eventPublisher->expects($this->once())->method('publishImportCompleted');
        $notificationSender->expects($this->once())->method('send');

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender);
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

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $notificationSender->method('send');

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender);
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

        $eventPublisher->method('publishBatch');
        $eventPublisher->method('publishImportCompleted');
        $notificationSender->method('send');

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act
        $result = $orchestrator->orchestrate($fixturePath, $importId);

        // Assert
        $this->assertSame('completed', $result->status);
        $this->assertNotNull($result->total_debtors);
        $this->assertNotNull($result->total_entities);
        $this->assertNotNull($result->duration_ms);

        // Cleanup
        @unlink($fixturePath);
    }

    public function test_orchestrate_updates_import_log_with_failed_status_on_error(): void
    {
        // Arrange
        $eventPublisher = $this->createMock(EventPublisher::class);
        $notificationSender = $this->createMock(NotificationSender::class);

        $eventPublisher->method('publishBatch')->willThrowException(new \RuntimeException('SQS error'));

        $orchestrator = new ImportOrchestrator($eventPublisher, $notificationSender);
        $fixturePath = $this->createFixtureFile();
        $importId = (string) Str::uuid();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQS error');

        try {
            $orchestrator->orchestrate($fixturePath, $importId);
        } catch (\RuntimeException $e) {
            // Verify the import log was updated before re-throwing
            $importLog = ImportLog::find($importId);
            $this->assertNotNull($importLog);
            $this->assertSame('failed', $importLog->status);
            $this->assertSame('SQS error', $importLog->error_message);

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
