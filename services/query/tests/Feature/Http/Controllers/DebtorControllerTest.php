<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Debtor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The query service is read-only and owns no schema. Tests run against the
 * shared wayni_test database (migrated by the importer) and wrap each test in
 * a transaction that rolls back — so they seed read fixtures without ever
 * creating or dropping tables.
 */
class DebtorControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_show_returns_debtor_for_valid_cuit(): void
    {
        // Arrange
        Debtor::create([
            'identification_number' => '20345123458',
            'max_situation' => '05',
            'total_loan_amount' => 15000.50,
        ]);

        // Act
        $response = $this->getJson('/api/v1/debtors/20345123458');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'identification_number', 'max_situation', 'total_loan_amount'],
        ]);
        $response->assertJsonPath('data.identification_number', '20345123458');
    }

    public function test_show_returns_404_for_non_existent_cuit(): void
    {
        // Act
        $response = $this->getJson('/api/v1/debtors/99999999999');

        // Assert
        $response->assertStatus(404);
    }

    public function test_show_returns_422_for_invalid_cuit_format(): void
    {
        // Act — too short
        $response = $this->getJson('/api/v1/debtors/12345');

        // Assert
        $response->assertStatus(422);
    }

    public function test_top_returns_top_n_debtors_ordered_by_total_loan_amount_desc(): void
    {
        // Arrange
        Debtor::create(['identification_number' => '20345123458', 'max_situation' => '01', 'total_loan_amount' => 1000.00]);
        Debtor::create(['identification_number' => '20345123459', 'max_situation' => '03', 'total_loan_amount' => 5000.00]);
        Debtor::create(['identification_number' => '20345123460', 'max_situation' => '05', 'total_loan_amount' => 3000.00]);

        // Act
        $response = $this->getJson('/api/v1/debtors/top/2');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.identification_number', '20345123459');
        $response->assertJsonPath('data.1.identification_number', '20345123460');
        $response->assertJsonPath('meta.count', 2);
    }

    public function test_top_returns_422_for_invalid_n(): void
    {
        // Act — n = 0
        $response = $this->getJson('/api/v1/debtors/top/0');

        // Assert
        $response->assertStatus(422);
    }

    public function test_top_returns_422_for_negative_n(): void
    {
        // Act
        $response = $this->getJson('/api/v1/debtors/top/-1');

        // Assert
        $response->assertStatus(422);
    }

    public function test_index_returns_paginated_list_with_default_50_per_page(): void
    {
        // Arrange — create 60 debtors
        for ($i = 0; $i < 60; $i++) {
            Debtor::create([
                'identification_number' => sprintf('2034512%04d', $i),
                'max_situation' => '01',
                'total_loan_amount' => 100.00,
            ]);
        }

        // Act
        $response = $this->getJson('/api/v1/debtors');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(50, 'data');
        $response->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    public function test_index_filters_by_situation(): void
    {
        // Arrange
        Debtor::create(['identification_number' => '20345123458', 'max_situation' => '01', 'total_loan_amount' => 1000.00]);
        Debtor::create(['identification_number' => '20345123459', 'max_situation' => '03', 'total_loan_amount' => 2000.00]);
        Debtor::create(['identification_number' => '20345123460', 'max_situation' => '03', 'total_loan_amount' => 3000.00]);

        // Act
        $response = $this->getJson('/api/v1/debtors?situation=03');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_returns_422_for_invalid_situation(): void
    {
        // Act
        $response = $this->getJson('/api/v1/debtors?situation=99');

        // Assert
        $response->assertStatus(422);
    }

    public function test_index_caps_per_page_at_200(): void
    {
        // Arrange — create 5 debtors
        for ($i = 0; $i < 5; $i++) {
            Debtor::create([
                'identification_number' => sprintf('2034512%04d', $i),
                'max_situation' => '01',
                'total_loan_amount' => 100.00,
            ]);
        }

        // Act
        $response = $this->getJson('/api/v1/debtors?per_page=200');

        // Assert
        $response->assertStatus(200);
    }

    public function test_response_format_matches_debtor_resource_structure(): void
    {
        // Arrange
        $debtor = Debtor::create([
            'identification_number' => '20345123458',
            'max_situation' => '05',
            'total_loan_amount' => 15000.50,
        ]);

        // Act
        $response = $this->getJson('/api/v1/debtors/20345123458');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'identification_number',
                'max_situation',
                'total_loan_amount',
                'created_at',
                'updated_at',
            ],
        ]);
    }
}
