<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\ImportCompletedEvent;
use PHPUnit\Framework\TestCase;

class ImportCompletedEventTest extends TestCase
{
    public function test_to_log_context_returns_snake_case_keys(): void
    {
        // Arrange
        $event = new ImportCompletedEvent(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalRecords: 9881,
            validRecords: 9881,
            invalidRecords: 0,
            durationMs: 8566,
        );

        // Act
        $context = $event->toLogContext();

        // Assert — log context uses snake_case keys
        $this->assertSame([
            'import_id' => 'import-123',
            'filename' => 'deudores.txt',
            'total_records' => 9881,
            'valid_records' => 9881,
            'invalid_records' => 0,
            'duration_ms' => 8566,
        ], $context);
    }
}
