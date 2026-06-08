<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_entity_for_valid_code(): void
    {
        // Arrange
        Entity::create([
            'entity_code' => '00001',
            'total_loan_amount' => 500000.00,
        ]);

        // Act
        $response = $this->getJson('/api/entities/00001');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'entity_code', 'total_loan_amount'],
        ]);
        $response->assertJsonPath('data.entity_code', '00001');
    }

    public function test_show_returns_404_for_non_existent_code(): void
    {
        // Act
        $response = $this->getJson('/api/entities/99999');

        // Assert
        $response->assertStatus(404);
    }

    public function test_show_returns_422_for_invalid_code_format(): void
    {
        // Act — too short
        $response = $this->getJson('/api/entities/123');

        // Assert
        $response->assertStatus(422);
    }

    public function test_response_format_matches_entity_resource_structure(): void
    {
        // Arrange
        Entity::create([
            'entity_code' => '00001',
            'total_loan_amount' => 500000.00,
        ]);

        // Act
        $response = $this->getJson('/api/entities/00001');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'entity_code',
                'total_loan_amount',
                'created_at',
                'updated_at',
            ],
        ]);
    }
}
