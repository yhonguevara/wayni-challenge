<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence;

use App\Application\DTOs\BcraRecordDTO;
use App\Infrastructure\Persistence\StagingLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration tests for StagingLoader.
 *
 * Uses a real database (wayni_test). Staging tables are created and dropped
 * within each test; RefreshDatabase resets import_logs between tests.
 */
class StagingLoaderTest extends TestCase
{
    use RefreshDatabase;

    private StagingLoader $loader;

    /** Fixed import UUID used across tests that need a consistent slug. */
    private const IMPORT_UUID = 'aabbccdd-1122-3344-5566-778899aabbcc';

    /** Slug derived from the UUID above: first 12 hex chars (dashes stripped). */
    private const IMPORT_SLUG = 'aabbccdd1122';

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        $this->loader = new StagingLoader($pdo);

        // Ensure staging table is absent before each test.
        $pdo->exec('DROP TABLE IF EXISTS raw_records_' . self::IMPORT_SLUG);
    }

    protected function tearDown(): void
    {
        // Clean up staging table after each test.
        DB::connection()->getPdo()->exec('DROP TABLE IF EXISTS raw_records_' . self::IMPORT_SLUG);

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createImportLog(string $importId = self::IMPORT_UUID, int $lastLoadedLine = 0): void
    {
        DB::table('import_logs')->insert([
            'id'               => $importId,
            'filename'         => 'test.txt',
            'status'           => 'processing',
            'last_loaded_line' => $lastLoadedLine,
        ]);
    }

    /**
     * Build a minimal BcraRecordDTO for staging tests.
     *
     * @param int    $lineNumber 1-indexed line number from the source file
     * @param string $cuit       Identification number (CUIT, 11 chars)
     * @param string $entity     Entity code (5 chars)
     * @param string $situation  Situation code (2 chars)
     * @param float  $loans      Loan amount
     */
    private function makeRecord(
        int $lineNumber,
        string $cuit = '20345123458',
        string $entity = 'BCO01',
        string $situation = '01',
        float $loans = 1000.00,
    ): BcraRecordDTO {
        return new BcraRecordDTO(
            entityCode: $entity,
            infoDate: '202601',
            identificationType: '11',
            identificationNumber: $cuit,
            activity: '001',
            situation: $situation,
            loans: $loans,
            unused: 0.0,
            guarantees: 0.0,
            otherConcepts: 0.0,
            preferredGuaranteesA: 0.0,
            preferredGuaranteesB: 0.0,
            noPreferredGuarantees: 0.0,
            counterGuaranteesA: 0.0,
            counterGuaranteesB: 0.0,
            noCounterGuarantees: 0.0,
            provisions: 0.0,
            debtCovered: '0',
            judicialProcess: '0',
            refinancing: '0',
            mandatoryRecat: '0',
            legalSituation: '0',
            technicalIrrecoverable: '0',
            daysOverdue: 0,
            lineNumber: $lineNumber,
        );
    }

    /**
     * Generate $count records with sequential line numbers starting at $startLine.
     *
     * @return list<BcraRecordDTO>
     */
    private function makeRecords(int $count, int $startLine = 1): array
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = $this->makeRecord($startLine + $i);
        }

        return $records;
    }

    // -----------------------------------------------------------------------
    // T2.1 — Chunk flush + UNLOGGED staging table
    // -----------------------------------------------------------------------

    public function test_flushes_rows_in_chunk_boundaries(): void
    {
        // Arrange: 12 001 rows — 2 full chunks of 5000 + 1 partial chunk of 2001
        $this->createImportLog();
        $records = $this->makeRecords(12_001);

        // Act
        $this->loader->load(self::IMPORT_UUID, $records);

        // Assert: all rows landed in staging
        $stagingCount = (int) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM raw_records_' . self::IMPORT_SLUG
        )->cnt;

        $this->assertSame(12_001, $stagingCount);
    }

    public function test_checkpoint_advances_to_last_loaded_line(): void
    {
        // Arrange: 12 001 rows
        $this->createImportLog();
        $records = $this->makeRecords(12_001);

        // Act
        $this->loader->load(self::IMPORT_UUID, $records);

        // Assert: last_loaded_line = 12 001
        $log = DB::table('import_logs')->where('id', self::IMPORT_UUID)->first();
        $this->assertSame(12_001, (int) $log->last_loaded_line);
    }

    public function test_staging_table_created_unlogged(): void
    {
        // Arrange
        $this->createImportLog();
        $records = $this->makeRecords(10);

        // Act
        $this->loader->load(self::IMPORT_UUID, $records);

        // Assert: relpersistence = 'u' means UNLOGGED in pg_class
        $row = DB::selectOne(
            "SELECT relpersistence FROM pg_class WHERE relname = ?",
            ['raw_records_' . self::IMPORT_SLUG]
        );

        $this->assertNotNull($row, 'Staging table raw_records_' . self::IMPORT_SLUG . ' not found in pg_class');
        $this->assertSame('u', $row->relpersistence, 'Staging table should be UNLOGGED (relpersistence=u)');
    }

    // -----------------------------------------------------------------------
    // T2.2 — Resume from checkpoint
    // -----------------------------------------------------------------------

    public function test_resume_skips_already_loaded_lines(): void
    {
        // Arrange: load first 3000 rows (below chunk threshold — no flush yet, but
        // we simulate a partial previous run by seeding the staging table directly
        // and setting last_loaded_line=3000).
        $this->createImportLog(lastLoadedLine: 3_000);

        // Seed staging table as if 3000 rows were already loaded.
        $pdo = DB::connection()->getPdo();
        $pdo->exec(
            'CREATE UNLOGGED TABLE raw_records_' . self::IMPORT_SLUG . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );

        // Insert 3000 placeholder rows (line numbers 1–3000).
        $batchSize = 500;
        for ($batch = 0; $batch < 3_000 / $batchSize; $batch++) {
            $rows = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = "('20345123458','BCO01','01',100.00)";
            }
            $pdo->exec(
                'INSERT INTO raw_records_' . self::IMPORT_SLUG
                . ' (identification_number, entity_code, situation, loans) VALUES '
                . implode(',', $rows)
            );
        }

        // Act: load records starting from line 3001 (2000 records, lines 3001–5000).
        $newRecords = $this->makeRecords(2_000, startLine: 3_001);
        $this->loader->load(self::IMPORT_UUID, $newRecords);

        // Assert: 3000 (pre-seeded) + 2000 (newly loaded) = 5000 total, no duplicates.
        $count = (int) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM raw_records_' . self::IMPORT_SLUG
        )->cnt;

        $this->assertSame(5_000, $count);

        // Checkpoint must reflect the last new line.
        $log = DB::table('import_logs')->where('id', self::IMPORT_UUID)->first();
        $this->assertSame(5_000, (int) $log->last_loaded_line);
    }

    public function test_fresh_start_when_last_loaded_line_is_zero(): void
    {
        // Arrange: last_loaded_line = 0 → StagingLoader must recreate the table.
        $this->createImportLog(lastLoadedLine: 0);

        // Pre-create a stale staging table as if a prior crashed run left it.
        $pdo = DB::connection()->getPdo();
        $pdo->exec(
            'CREATE UNLOGGED TABLE raw_records_' . self::IMPORT_SLUG . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );
        $pdo->exec(
            'INSERT INTO raw_records_' . self::IMPORT_SLUG
            . ' (identification_number, entity_code, situation, loans) VALUES (\'20345123458\',\'BCO01\',\'01\',100.00)'
        );

        // Act: load 4000 new rows from the start.
        $records = $this->makeRecords(4_000, startLine: 1);
        $this->loader->load(self::IMPORT_UUID, $records);

        // Assert: exactly 4000 rows (stale row was dropped).
        $count = (int) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM raw_records_' . self::IMPORT_SLUG
        )->cnt;

        $this->assertSame(4_000, $count);
    }

    // -----------------------------------------------------------------------
    // T2.3 — GC orphan staging tables
    // -----------------------------------------------------------------------

    public function test_gc_drops_stale_completed_tables(): void
    {
        // Arrange: create a raw_records_ table and a matching completed import_log
        // with updated_at > 24 hours ago.
        $pdo = DB::connection()->getPdo();
        $orphanSlug = 'abc123def456';
        $orphanTable = 'raw_records_' . $orphanSlug;

        // Derive a UUID whose first-12-hex matches the slug.
        // slug = substr(str_replace('-','',uuid), 0, 12)
        // We need: first 12 hex chars of UUID (dashes stripped) = 'abc123def456'
        // So UUID = 'abc123de-f456-xxxx-xxxx-xxxxxxxxxxxx'
        $orphanImportId = 'abc123de-f456-0000-0000-000000000001';

        $pdo->exec(
            'CREATE UNLOGGED TABLE ' . $orphanTable . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );

        // Insert import_log with completed status and updated_at 25 hours ago.
        DB::table('import_logs')->insert([
            'id'         => $orphanImportId,
            'filename'   => 'old.txt',
            'status'     => 'completed',
            'created_at' => now()->subHours(26),
            'updated_at' => now()->subHours(25),
        ]);

        // Act
        $this->loader->gcOrphans();

        // Assert: table is gone
        $exists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$orphanTable]
        );
        $this->assertNull($exists, "Expected $orphanTable to be dropped by gcOrphans()");

        // Cleanup
        $pdo->exec('DROP TABLE IF EXISTS ' . $orphanTable);
    }

    public function test_gc_retains_recent_staging_tables(): void
    {
        // Arrange: create a raw_records_ table and a matching import_log
        // with updated_at only 1 hour ago — NOT stale enough to GC.
        $pdo = DB::connection()->getPdo();
        $recentSlug = 'def456abc123';
        $recentTable = 'raw_records_' . $recentSlug;
        $recentImportId = 'def456ab-c123-0000-0000-000000000001';

        $pdo->exec(
            'CREATE UNLOGGED TABLE ' . $recentTable . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );

        DB::table('import_logs')->insert([
            'id'         => $recentImportId,
            'filename'   => 'recent.txt',
            'status'     => 'completed',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        // Act
        $this->loader->gcOrphans();

        // Assert: table still exists
        $exists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$recentTable]
        );
        $this->assertNotNull($exists, "Expected $recentTable to be retained by gcOrphans()");

        // Cleanup
        $pdo->exec('DROP TABLE IF EXISTS ' . $recentTable);
    }

    // -----------------------------------------------------------------------
    // dropStaging
    // -----------------------------------------------------------------------

    public function test_drop_staging_removes_table(): void
    {
        // Arrange
        $this->createImportLog();
        $this->loader->load(self::IMPORT_UUID, $this->makeRecords(5));

        // Act
        $this->loader->dropStaging(self::IMPORT_UUID);

        // Assert
        $exists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            ['raw_records_' . self::IMPORT_SLUG]
        );
        $this->assertNull($exists, 'Staging table should be dropped');
    }
}
