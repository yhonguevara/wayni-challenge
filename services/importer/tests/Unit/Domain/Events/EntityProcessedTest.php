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
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        // Act
        $event = new EntityProcessed(
            entityCode: $entityCode,
            totalLoans: $totalLoans,
            importId: $importId,
            processedAt: $processedAt,
        );

        // Assert
        $this->assertSame('00001', $event->entityCode);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
        $this->assertSame($processedAt, $event->processedAt);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('00001', $event->entityCode);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
        $this->assertSame($processedAt, $event->processedAt);
    }

    public function test_to_array_returns_correct_payload(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
        );

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame('00001', $array['entityCode']);
        $this->assertSame(1500.5, $array['totalLoans']);
        $this->assertSame('import-123', $array['importId']);
        $this->assertSame('2026-06-08T12:00:00Z', $array['occurredAt']);
    }

    public function test_occurred_at_returns_processed_at(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
        );

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $occurredAt);
        $this->assertSame($processedAt, $occurredAt);
    }

    public function test_occurred_at_defaults_to_current_time(): void
    {
        // Arrange
        $before = new \DateTimeImmutable();

        $event = new EntityProcessed(
            entityCode: '00001',
            totalLoans: 1500.5,
            importId: 'import-123',
        );

        $after = new \DateTimeImmutable();

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertGreaterThanOrEqual($before, $occurredAt);
        $this->assertLessThanOrEqual($after, $occurredAt);
    }
}
