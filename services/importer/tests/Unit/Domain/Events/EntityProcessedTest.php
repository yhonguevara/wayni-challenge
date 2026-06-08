<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Events;

use App\Domain\Events\EntityProcessed;
use PHPUnit\Framework\TestCase;

class EntityProcessedTest extends TestCase
{
    public function test_construction_with_all_properties(): void
    {
        // Arrange
        $entityCode = '00001';
        $totalLoans = 1500.5;
        $importId = 'import-123';

        // Act
        $event = new EntityProcessed(
            entityCode: $entityCode,
            totalLoans: $totalLoans,
            importId: $importId,
        );

        // Assert
        $this->assertSame('00001', $event->entityCode);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('00001', $event->entityCode);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
    }

    public function test_to_array_returns_correct_payload(): void
    {
        // Arrange
        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
        );

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame('00001', $array['entityCode']);
        $this->assertSame(1500.5, $array['totalLoans']);
        $this->assertSame('import-123', $array['importId']);
        $this->assertArrayHasKey('occurredAt', $array);
    }

    public function test_occurred_at_returns_date_time_immutable(): void
    {
        // Arrange
        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
        );

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $occurredAt);
    }
}
