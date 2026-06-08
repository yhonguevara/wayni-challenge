<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\DTOs\EntityProcessedEvent;
use App\Infrastructure\Handlers\UpsertEntityHandler;
use App\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertEntityHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_creates_new_entity_record(): void
    {
        // Arrange
        $event = new EntityProcessedEvent(
            entityCode: '00001',
            totalLoans: 500000.00,
            importId: 'import-001',
        );
        $handler = new UpsertEntityHandler();

        // Act
        $handler->handle($event);

        // Assert
        $this->assertDatabaseHas('entities', [
            'entity_code' => '00001',
        ]);
    }

    public function test_handle_updates_existing_entity_idempotent(): void
    {
        // Arrange
        Entity::create([
            'entity_code' => '00001',
            'total_loan_amount' => 100000.00,
        ]);

        $event = new EntityProcessedEvent(
            entityCode: '00001',
            totalLoans: 500000.00,
            importId: 'import-001',
        );
        $handler = new UpsertEntityHandler();

        // Act
        $handler->handle($event);

        // Assert — no duplicate rows
        $this->assertSame(1, Entity::where('entity_code', '00001')->count());
        $entity = Entity::where('entity_code', '00001')->first();
        $this->assertEquals(500000.00, (float) $entity->total_loan_amount);
    }

    public function test_handle_deserializes_event_correctly(): void
    {
        // Arrange
        $event = EntityProcessedEvent::fromArray([
            'entityCode' => '00002',
            'totalLoans' => 250000.0,
            'importId' => 'import-002',
        ]);
        $handler = new UpsertEntityHandler();

        // Act
        $handler->handle($event);

        // Assert
        $this->assertDatabaseHas('entities', [
            'entity_code' => '00002',
        ]);
    }
}
