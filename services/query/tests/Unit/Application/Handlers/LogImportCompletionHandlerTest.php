<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\DTOs\ImportCompletedEvent;
use App\Infrastructure\Handlers\LogImportCompletionHandler;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogImportCompletionHandlerTest extends TestCase
{
    public function test_handle_logs_structured_message(): void
    {
        // Arrange
        $event = new ImportCompletedEvent(
            importId: 'import-001',
            filename: 'deudores.txt',
            totalRecords: 155,
            validRecords: 150,
            invalidRecords: 5,
            durationMs: 2500,
        );
        $handler = new LogImportCompletionHandler();

        Log::shouldReceive('info')
            ->once()
            ->with('Import completed', $event->toArray());

        // Act
        $handler->handle($event);

        // Assert — Mockery expectations verified
    }

    public function test_handle_deserializes_event_correctly(): void
    {
        // Arrange
        $event = ImportCompletedEvent::fromArray([
            'importId' => 'import-002',
            'filename' => 'test.txt',
            'totalRecords' => 100,
            'validRecords' => 95,
            'invalidRecords' => 5,
            'durationMs' => 1000,
        ]);
        $handler = new LogImportCompletionHandler();

        Log::shouldReceive('info')->once();

        // Act
        $handler->handle($event);

        // Assert
        $this->assertSame('import-002', $event->importId);
        $this->assertSame('test.txt', $event->filename);
        $this->assertSame(100, $event->totalRecords);
    }
}
