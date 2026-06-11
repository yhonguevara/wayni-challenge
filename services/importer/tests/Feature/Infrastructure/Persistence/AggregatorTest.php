<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Aggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration tests for Aggregator.
 *
 * Validates the severity-correct GROUP BY INSERT for debtors and entities.
 * The staging table is seeded directly (no StagingLoader dependency) so these
 * tests isolate Aggregator's SQL logic.
 *
 * Critical correctness contract:
 * - Severity order: 01 < 11 < 21 < 23 < 03 < 04 < 05
 * - '05' beats '23' beats '21', etc.
 * - SUM(loans) is additive across all rows for the same CUIT / entity_code.
 * - TRUNCATE semantics: each aggregate() call replaces all debtors/entities.
 */
class AggregatorTest extends TestCase
{
    use RefreshDatabase;

    private Aggregator $aggregator;

    /** Import UUID and derived slug used for the test staging table. */
    private const IMPORT_UUID = 'ccddee00-1122-3344-5566-778899aabbcc';
    private const IMPORT_SLUG = 'ccddee001122';
    private const STAGING_TABLE = 'raw_records_ccddee001122';

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = new Aggregator(DB::connection()->getPdo());

        // Create the staging table for this import.
        DB::connection()->getPdo()->exec(
            'CREATE UNLOGGED TABLE ' . self::STAGING_TABLE . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );
    }

    protected function tearDown(): void
    {
        DB::connection()->getPdo()->exec('DROP TABLE IF EXISTS ' . self::STAGING_TABLE);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function insertStagingRow(
        string $cuit,
        string $entity,
        string $situation,
        float $loans,
    ): void {
        DB::statement(
            'INSERT INTO ' . self::STAGING_TABLE
            . ' (identification_number, entity_code, situation, loans) VALUES (?,?,?,?)',
            [$cuit, $entity, $situation, $loans]
        );
    }

    // -----------------------------------------------------------------------
    // Debtor severity correctness (CRITICAL)
    // -----------------------------------------------------------------------

    public function test_debtor_situation_05_beats_23(): void
    {
        // Arrange: same CUIT with two situations; 05 must win over 23.
        $this->insertStagingRow('20345123458', 'BCO01', '05', 100.00);
        $this->insertStagingRow('20345123458', 'BCO01', '23', 200.00);

        // Act
        $this->aggregator->aggregate(self::IMPORT_UUID);

        // Assert
        $debtor = DB::table('debtors')->where('identification_number', '20345123458')->first();
        $this->assertNotNull($debtor);
        $this->assertSame('05', $debtor->max_situation);
        $this->assertEquals('300.00', $debtor->total_loan_amount);
    }

    public function test_debtor_situation_23_beats_21(): void
    {
        // Arrange
        $this->insertStagingRow('20345123459', 'BCO01', '23', 150.00);
        $this->insertStagingRow('20345123459', 'BCO01', '21', 100.00);

        // Act
        $this->aggregator->aggregate(self::IMPORT_UUID);

        // Assert
        $debtor = DB::table('debtors')->where('identification_number', '20345123459')->first();
        $this->assertNotNull($debtor);
        $this->assertSame('23', $debtor->max_situation);
        $this->assertEquals('250.00', $debtor->total_loan_amount);
    }

    public function test_debtor_situation_05_beats_23_regardless_of_row_order(): void
    {
        // Same as above but rows inserted in reverse order — SQL must not depend on row order.
        $this->insertStagingRow('20345123460', 'BCO01', '23', 200.00);
        $this->insertStagingRow('20345123460', 'BCO01', '05', 100.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $debtor = DB::table('debtors')->where('identification_number', '20345123460')->first();
        $this->assertSame('05', $debtor->max_situation);
    }

    public function test_debtor_situation_01_alone(): void
    {
        // Single row — no aggregation needed, just confirms the CASE mapping works.
        $this->insertStagingRow('20345123461', 'BCO01', '01', 500.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $debtor = DB::table('debtors')->where('identification_number', '20345123461')->first();
        $this->assertNotNull($debtor);
        $this->assertSame('01', $debtor->max_situation);
        $this->assertEquals('500.00', $debtor->total_loan_amount);
    }

    public function test_debtor_03_beats_23(): void
    {
        // 03 rank=5, 23 rank=4 → 03 wins.
        $this->insertStagingRow('20345123462', 'BCO01', '23', 100.00);
        $this->insertStagingRow('20345123462', 'BCO01', '03', 100.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $debtor = DB::table('debtors')->where('identification_number', '20345123462')->first();
        $this->assertSame('03', $debtor->max_situation);
    }

    public function test_debtor_loans_are_summed_correctly(): void
    {
        // Three rows for same CUIT — loans must be summed, not the last-row value.
        $this->insertStagingRow('20345123463', 'BCO01', '01', 100.00);
        $this->insertStagingRow('20345123463', 'BCO01', '11', 200.00);
        $this->insertStagingRow('20345123463', 'BCO01', '21', 300.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $debtor = DB::table('debtors')->where('identification_number', '20345123463')->first();
        $this->assertEquals('600.00', $debtor->total_loan_amount);
        $this->assertSame('21', $debtor->max_situation);
    }

    // -----------------------------------------------------------------------
    // Entity aggregation
    // -----------------------------------------------------------------------

    public function test_entity_total_loan_amount_summed(): void
    {
        $this->insertStagingRow('20345123464', 'BCO01', '01', 300.00);
        $this->insertStagingRow('20345123465', 'BCO01', '03', 700.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $entity = DB::table('entities')->where('entity_code', 'BCO01')->first();
        $this->assertNotNull($entity);
        $this->assertEquals('1000.00', $entity->total_loan_amount);
    }

    public function test_multiple_entities_aggregated_independently(): void
    {
        $this->insertStagingRow('20345123466', 'BCO01', '01', 100.00);
        $this->insertStagingRow('20345123467', 'BCO02', '01', 200.00);
        $this->insertStagingRow('20345123468', 'BCO01', '01', 50.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $bco01 = DB::table('entities')->where('entity_code', 'BCO01')->first();
        $bco02 = DB::table('entities')->where('entity_code', 'BCO02')->first();

        $this->assertEquals('150.00', $bco01->total_loan_amount);
        $this->assertEquals('200.00', $bco02->total_loan_amount);
    }

    // -----------------------------------------------------------------------
    // TRUNCATE semantics (latest file wins)
    // -----------------------------------------------------------------------

    public function test_aggregate_truncates_existing_debtors_before_insert(): void
    {
        // Pre-populate debtors with a CUIT not in staging.
        DB::table('debtors')->insert([
            'identification_number' => '99999999999',
            'max_situation'         => '01',
            'total_loan_amount'     => 9999.00,
        ]);

        // Staging only has a different CUIT.
        $this->insertStagingRow('20345123469', 'BCO01', '01', 100.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        // Old CUIT must be gone (TRUNCATE before INSERT).
        $stale = DB::table('debtors')->where('identification_number', '99999999999')->first();
        $this->assertNull($stale, 'TRUNCATE should have removed old debtors row');

        // New CUIT is present.
        $fresh = DB::table('debtors')->where('identification_number', '20345123469')->first();
        $this->assertNotNull($fresh);
    }

    public function test_aggregate_truncates_existing_entities_before_insert(): void
    {
        DB::table('entities')->insert([
            'entity_code'       => 'STALE',
            'total_loan_amount' => 9999.00,
        ]);

        $this->insertStagingRow('20345123470', 'BCO01', '01', 100.00);

        $this->aggregator->aggregate(self::IMPORT_UUID);

        $stale = DB::table('entities')->where('entity_code', 'STALE')->first();
        $this->assertNull($stale, 'TRUNCATE should have removed old entities row');
    }
}
