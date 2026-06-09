<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Console;

use App\Application\DTOs\ImportCompletedEvent;
use App\Infrastructure\Console\ConsumeEventsCommand;
use Tests\TestCase;

class ConsumeEventsCommandTest extends TestCase
{
    public function test_map_import_completed_derives_record_counts_from_debtor_and_entity_totals(): void
    {
        // Arrange — the importer publishes this shape (no totalRecords/importId).
        $publishedBody = [
            'filename' => 'deudores_bcra.txt',
            'totalDebtors' => 1966,
            'totalEntities' => 12,
            'durationMs' => 1804,
            'completedAt' => '2026-06-09T00:00:00Z',
        ];

        // Act
        $mapped = ConsumeEventsCommand::mapImportCompleted($publishedBody);

        // Assert — the query DTO can be built from the mapped shape.
        $event = ImportCompletedEvent::fromArray($mapped);
        $this->assertSame('deudores_bcra.txt', $event->filename);
        $this->assertSame(1978, $event->totalRecords); // 1966 + 12
        $this->assertSame(1978, $event->validRecords);
        $this->assertSame(0, $event->invalidRecords);
        $this->assertSame(1804, $event->durationMs);
    }

    public function test_map_import_completed_tolerates_missing_keys(): void
    {
        // Act — defensive: a partial payload must not throw.
        $mapped = ConsumeEventsCommand::mapImportCompleted([]);

        // Assert
        $event = ImportCompletedEvent::fromArray($mapped);
        $this->assertSame('', $event->filename);
        $this->assertSame(0, $event->totalRecords);
        $this->assertSame(0, $event->durationMs);
    }
}
