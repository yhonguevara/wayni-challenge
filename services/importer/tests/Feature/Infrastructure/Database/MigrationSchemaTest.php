<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies that all expected tables and columns exist after migrations run.
 *
 * Tests follow RED → GREEN order (written before migrations are created).
 */
class MigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // debtors table
    // -----------------------------------------------------------------------

    public function test_debtors_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('debtors'));
    }

    public function test_debtors_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('debtors', [
            'id',
            'identification_number',
            'max_situation',
            'total_loan_amount',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_debtors_identification_number_is_unique(): void
    {
        // Arrange — first insert succeeds
        \DB::table('debtors')->insert([
            'identification_number' => '20345123458',
            'max_situation'         => '01',
            'total_loan_amount'     => 1000.00,
        ]);

        // Act + Assert — second insert with same CUIT throws
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('debtors')->insert([
            'identification_number' => '20345123458',
            'max_situation'         => '03',
            'total_loan_amount'     => 500.00,
        ]);
    }

    // -----------------------------------------------------------------------
    // entities table
    // -----------------------------------------------------------------------

    public function test_entities_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('entities'));
    }

    public function test_entities_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('entities', [
            'id',
            'entity_code',
            'total_loan_amount',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_entities_entity_code_is_unique(): void
    {
        \DB::table('entities')->insert([
            'entity_code'       => 'BCO01',
            'total_loan_amount' => 50000.00,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('entities')->insert([
            'entity_code'       => 'BCO01',
            'total_loan_amount' => 75000.00,
        ]);
    }

    // -----------------------------------------------------------------------
    // import_logs columns
    // -----------------------------------------------------------------------

    public function test_import_logs_has_last_loaded_line_column(): void
    {
        $this->assertTrue(Schema::hasColumn('import_logs', 'last_loaded_line'));
    }

    public function test_import_logs_last_loaded_line_default_is_zero(): void
    {
        // Insert a row without specifying last_loaded_line — the column default must be 0.
        $importId = \Str::uuid()->toString();
        \DB::table('import_logs')->insert([
            'id'       => $importId,
            'filename' => 'test.txt',
            'status'   => 'pending',
        ]);

        $row = \DB::table('import_logs')->where('id', $importId)->first();
        $this->assertSame(0, (int) $row->last_loaded_line);
    }

}
