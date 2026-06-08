<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Events;

use App\Domain\Events\ImportCompleted;
use PHPUnit\Framework\TestCase;

class ImportCompletedTest extends TestCase
{
    public function test_construction_with_all_properties(): void
    {
        // Arrange
        $importId = 'import-123';
        $totalRecords = 100;
        $validRecords = 95;
        $invalidRecords = 5;
        $durationMs = 1500;

        // Act
        $event = new ImportCompleted(
            importId: $importId,
            totalRecords: $totalRecords,
            validRecords: $validRecords,
            invalidRecords: $invalidRecords,
            durationMs: $durationMs,
        );

        // Assert
        $this->assertSame('import-123', $event->importId);
        $this->assertSame(100, $event->totalRecords);
        $this->assertSame(95, $event->validRecords);
        $this->assertSame(5, $event->invalidRecords);
        $this->assertSame(1500, $event->durationMs);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $event = new ImportCompleted(
            importId: 'import-123',
            totalRecords: 100,
            validRecords: 95,
            invalidRecords: 5,
            durationMs: 1500,
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('import-123', $event->importId);
        $this->assertSame(100, $event->totalRecords);
        $this->assertSame(95, $event->validRecords);
        $this->assertSame(5, $event->invalidRecords);
        $this->assertSame(1500, $event->durationMs);
    }

    public function test_to_array_returns_correct_payload(): void
    {
        // Arrange
        $event = new ImportCompleted(
            importId: 'import-123',
            totalRecords: 100,
            validRecords: 95,
            invalidRecords: 5,
            durationMs: 1500,
        );

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame('import-123', $array['importId']);
        $this->assertSame(100, $array['totalRecords']);
        $this->assertSame(95, $array['validRecords']);
        $this->assertSame(5, $array['invalidRecords']);
        $this->assertSame(1500, $array['durationMs']);
        $this->assertArrayHasKey('occurredAt', $array);
    }

    public function test_occurred_at_returns_date_time_immutable(): void
    {
        // Arrange
        $event = new ImportCompleted(
            importId: 'import-123',
            totalRecords: 100,
            validRecords: 95,
            invalidRecords: 5,
            durationMs: 1500,
        );

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $occurredAt);
    }
}
