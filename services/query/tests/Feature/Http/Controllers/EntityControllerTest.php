<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Entity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The query service is read-only and owns no schema. Tests run against the
 * shared wayni_test database (migrated by the importer) and wrap each test in
 * a transaction that rolls back — so they seed read fixtures without ever
 * creating or dropping tables.
 */
class EntityControllerTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_show_returns_entity_for_short_code(): void
    {
        // Arrange
        Entity::create([
            'entity_code' => '1',
            'total_loan_amount' => 100000.00,
        ]);

        // Act
        $response = $this->getJson('/api/entities/1');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.entity_code', '1');
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
        // Act — too long (exceeds 5 characters)
        $response = $this->getJson('/api/entities/123456');

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
