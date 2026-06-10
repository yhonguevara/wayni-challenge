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
        $filename = 'deudores.txt';
        $totalDebtors = 150;
        $totalEntities = 5;
        $durationMs = 2500;
        $completedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        // Act
        $event = new ImportCompleted(
            importId: $importId,
            filename: $filename,
            totalDebtors: $totalDebtors,
            totalEntities: $totalEntities,
            durationMs: $durationMs,
            completedAt: $completedAt,
        );

        // Assert
        $this->assertSame('import-123', $event->importId);
        $this->assertSame('deudores.txt', $event->filename);
        $this->assertSame(150, $event->totalDebtors);
        $this->assertSame(5, $event->totalEntities);
        $this->assertSame(2500, $event->durationMs);
        $this->assertSame($completedAt, $event->completedAt);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $completedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: $completedAt,
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('import-123', $event->importId);
        $this->assertSame('deudores.txt', $event->filename);
        $this->assertSame(150, $event->totalDebtors);
        $this->assertSame(5, $event->totalEntities);
        $this->assertSame(2500, $event->durationMs);
        $this->assertSame($completedAt, $event->completedAt);
    }

    public function test_to_array_returns_correct_payload(): void
    {
        // Arrange
        $completedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: $completedAt,
        );

        // Act
        $array = $event->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame('import-123', $array['importId']);
        $this->assertSame('deudores.txt', $array['filename']);
        $this->assertSame(150, $array['totalDebtors']);
        $this->assertSame(5, $array['totalEntities']);
        $this->assertSame(2500, $array['durationMs']);
        $this->assertSame('2026-06-08T12:00:00Z', $array['completedAt']);
    }

    public function test_to_snake_case_returns_snake_case_keys(): void
    {
        // Arrange
        $completedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: $completedAt,
        );

        // Act
        $payload = $event->toSnakeCase();

        // Assert — public-facing output uses snake_case keys, matching the REST API
        $this->assertSame([
            'import_id' => 'import-123',
            'filename' => 'deudores.txt',
            'total_debtors' => 150,
            'total_entities' => 5,
            'duration_ms' => 2500,
            'completed_at' => '2026-06-08T12:00:00Z',
        ], $payload);
    }

    public function test_occurred_at_returns_completed_at(): void
    {
        // Arrange
        $completedAt = new \DateTimeImmutable('2026-06-08T12:00:00Z');

        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: $completedAt,
        );

        // Act
        $occurredAt = $event->occurredAt();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $occurredAt);
        $this->assertSame($completedAt, $occurredAt);
    }
}
