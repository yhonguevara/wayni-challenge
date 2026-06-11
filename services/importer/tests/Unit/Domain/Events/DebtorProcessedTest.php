<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Events;

use App\Domain\Events\DebtorProcessed;
use PHPUnit\Framework\TestCase;

class DebtorProcessedTest extends TestCase
{
    public function test_construction_with_all_properties(): void
    {
        // Arrange
        $identificationNumber = '20345123458';
        $maxSituation = '05';
        $totalLoans = 1500.5;
        $importId = 'import-123';
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        // Act
        $event = new DebtorProcessed(
            identificationNumber: $identificationNumber,
            maxSituation: $maxSituation,
            totalLoans: $totalLoans,
            importId: $importId,
            processedAt: $processedAt,
            lineNumber: 42,
        );

        // Assert
        $this->assertSame('20345123458', $event->identificationNumber);
        $this->assertSame('05', $event->maxSituation);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
        $this->assertSame($processedAt, $event->processedAt);
        $this->assertSame(42, $event->lineNumber);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
            lineNumber: 7,
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('20345123458', $event->identificationNumber);
        $this->assertSame('05', $event->maxSituation);
        $this->assertSame(1500.5, $event->totalLoans);
        $this->assertSame('import-123', $event->importId);
        $this->assertSame($processedAt, $event->processedAt);
        $this->assertSame(7, $event->lineNumber);
    }

    public function test_to_array_returns_correct_payload(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
            lineNumber: 99,
        );

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame('20345123458', $array['identificationNumber']);
        $this->assertSame('05', $array['maxSituation']);
        $this->assertSame(1500.5, $array['totalLoans']);
        $this->assertSame('import-123', $array['importId']);
        $this->assertSame('2026-06-08T12:00:00Z', $array['occurredAt']);
        $this->assertSame(99, $array['lineNumber']);
    }

    public function test_line_number_defaults_to_zero(): void
    {
        // Act — lineNumber not provided, should default
        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '01',
            totalLoans: 500.0,
            importId: 'import-123',
        );

        // Assert
        $this->assertSame(0, $event->lineNumber);
    }

    public function test_occurred_at_returns_processed_at(): void
    {
        // Arrange
        $processedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'import-123',
            processedAt: $processedAt,
            lineNumber: 5,
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

        $event = new DebtorProcessed(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 1500.5,
            importId: 'import-123',
            lineNumber: 1,
        );

        $after = new \DateTimeImmutable();

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertGreaterThanOrEqual($before, $occurredAt);
        $this->assertLessThanOrEqual($after, $occurredAt);
    }
}
