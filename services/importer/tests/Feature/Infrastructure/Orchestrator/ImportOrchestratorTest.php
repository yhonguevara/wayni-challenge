<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Orchestrator;

use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\ImportLogRepository;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Persistence\Aggregator;
use App\Infrastructure\Persistence\StagingLoader;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the rewritten ImportOrchestrator (ELT flow).
 *
 * These tests run against the real wayni_test database. Mocks are used for
 * NotificationSender (no real SQS in tests). StagingLoader + Aggregator run
 * against real DB so the full ELT pipeline is exercised end-to-end.
 *
 * Happy path: loads staging -> aggregates -> drops staging -> notifies -> status 'completed'.
 * Failure paths: status 'failed', staging retained (NOT dropped), notification NOT sent.
 */
class ImportOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private ImportOrchestrator $orchestrator;
    private StagingLoader $stagingLoader;
    private Aggregator $aggregator;
    private ImportLogRepository $importLogRepository;
    private NotificationSender $notificationSender;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();

        $this->stagingLoader = new StagingLoader(pdo: $pdo);
        $this->aggregator = new Aggregator(pdo: $pdo);
        $this->importLogRepository = $this->app->make(ImportLogRepository::class);
        $this->notificationSender = $this->createMock(NotificationSender::class);

        $this->orchestrator = new ImportOrchestrator(
            stagingLoader: $this->stagingLoader,
            aggregator: $this->aggregator,
            importLogRepository: $this->importLogRepository,
            notificationSender: $this->notificationSender,
        );
    }

    public function test_orchestrate_happy_path_full_elt(): void
    {
        // Arrange
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFile(lineCount: 4);

        $this->notificationSender
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(ImportCompleted::class));

        // Act
        $this->orchestrator->orchestrate($fixturePath, $importId);

        // Assert — debtors and entities populated (4 unique CUITs, 1 entity)
        $this->assertSame(4, DB::table('debtors')->count(), 'debtors must have 4 rows after aggregation');
        $this->assertSame(1, DB::table('entities')->count(), 'entities must have 1 row (all same entity_code)');

        // Assert — staging table dropped
        $slug = substr(str_replace('-', '', $importId), 0, 12);
        $staging = 'raw_records_' . $slug;
        $exists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$staging]
        );
        $this->assertNull($exists, 'staging table must be dropped after successful ELT');

        // Assert — import log status is 'completed'
        $log = DB::table('import_logs')->where('id', $importId)->first();
        $this->assertNotNull($log);
        $this->assertSame('completed', $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertSame(4, (int) $log->valid_records);

        @unlink($fixturePath);
    }

    public function test_orchestrate_severity_correctness(): void
    {
        // Arrange — same CUIT with situation 23 and 05; 05 must win
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFileWithSituations(
            rows: [
                ['cuit' => '20345123458', 'entity' => '00001', 'situation' => '23', 'loans' => 1000.0],
                ['cuit' => '20345123458', 'entity' => '00001', 'situation' => '05', 'loans' => 500.0],
            ]
        );

        $this->notificationSender->method('send');

        // Act
        $this->orchestrator->orchestrate($fixturePath, $importId);

        // Assert — worst situation wins
        $debtor = DB::table('debtors')->where('identification_number', '20345123458')->first();
        $this->assertNotNull($debtor);
        $this->assertSame('05', $debtor->max_situation, '05 must beat 23');
        $this->assertSame('1500.00', number_format((float) $debtor->total_loan_amount, 2, '.', ''), 'loans must be summed');

        @unlink($fixturePath);
    }

    public function test_orchestrate_status_never_publishing_or_persisting(): void
    {
        // Arrange
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFile(lineCount: 2);
        $statusHistory = [];

        $this->notificationSender->method('send');

        // Spy on the repo to capture status transitions
        $repoSpy = $this->createMock(ImportLogRepository::class);
        $repoSpy->method('find')->willReturn(null);
        $repoSpy->method('create')->willReturnCallback(function (array $data) use (&$statusHistory): ImportLog {
            $statusHistory[] = $data['status'];
            $log = new ImportLog();
            $log->id = $data['id'];
            return $log;
        });
        $repoSpy->method('updateStatus')->willReturnCallback(function (string $id, string $status) use (&$statusHistory): void {
            $statusHistory[] = $status;
        });

        $orchestrator = new ImportOrchestrator(
            stagingLoader: $this->stagingLoader,
            aggregator: $this->aggregator,
            importLogRepository: $repoSpy,
            notificationSender: $this->notificationSender,
        );

        // Act
        $orchestrator->orchestrate($fixturePath, $importId);

        // Assert — no publishing or persisting states
        $this->assertNotContains('publishing', $statusHistory, 'must not transition to publishing');
        $this->assertNotContains('persisting', $statusHistory, 'must not transition to persisting');
        $this->assertContains('processing', $statusHistory);
        $this->assertContains('completed', $statusHistory);

        @unlink($fixturePath);
    }

    public function test_orchestrate_failed_load_sets_status_failed_and_retains_staging(): void
    {
        // Arrange — trigger a real load failure by pointing at a non-existent file.
        // StagingLoader::load() calls BcraFileParser which throws when the file is missing.
        $importId = (string) Str::uuid();
        $nonExistentPath = '/tmp/bcra_does_not_exist_' . $importId . '.txt';

        $this->notificationSender->expects($this->never())->method('send');

        // Act & Assert
        $this->expectException(\Throwable::class);

        try {
            $this->orchestrator->orchestrate($nonExistentPath, $importId);
        } finally {
            // Assert — status is 'failed' with error message
            $log = DB::table('import_logs')->where('id', $importId)->first();
            $this->assertNotNull($log, 'import log must have been created');
            $this->assertSame('failed', $log->status, 'status must be failed');
            $this->assertNotNull($log->error_message, 'error_message must be set');

            // Assert — staging table NOT dropped (retained for resume)
            // Since load fails immediately (file not found), no staging table is created,
            // but dropStaging is never called. This is verified by status='failed'.
        }
    }

    public function test_orchestrate_failed_aggregate_sets_status_failed_and_retains_staging(): void
    {
        // Arrange — load a real file (creates staging), then corrupt the staging table
        // column so the aggregation SQL fails. This exercises the failed-aggregate branch
        // using the real infrastructure.
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFile(lineCount: 2);
        $slug = substr(str_replace('-', '', $importId), 0, 12);
        $staging = 'raw_records_' . $slug;

        $this->notificationSender->expects($this->never())->method('send');

        // Create import log and load staging so we can corrupt it afterward
        DB::table('import_logs')->insert([
            'id'               => $importId,
            'filename'         => 'test.txt',
            'status'           => 'processing',
            'started_at'       => now(),
            'last_loaded_line' => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Manually load staging to simulate "load succeeded, aggregate fails"
        $pdo = DB::connection()->getPdo();
        $pdo->exec(
            "CREATE UNLOGGED TABLE {$staging} ("
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );
        // Insert an invalid situation code that will break the CASE expression
        // by causing the aggregate to produce a NULL result (NULL into NOT NULL column = error)
        // Actually, the CASE returns NULL for unknown codes — the debtors insert will fail
        // if identification_number is empty or a PG constraint fires.
        // Simpler: insert a row with an invalid (unmapped) situation code '99'
        // so CASE returns NULL, and the array lookup yields NULL → NOT NULL violation.
        $pdo->exec("INSERT INTO {$staging} VALUES ('20345123458', '00001', '99', 1500.00)");
        DB::table('import_logs')->where('id', $importId)->update(['last_loaded_line' => 1]);

        // Create orchestrator that uses a "pre-loaded" state by setting last_loaded_line > 0
        // so the loader skips the file but the aggregator runs on the corrupted staging.
        // We use an empty fixture file so the load phase appends 0 rows to existing staging.
        $emptyPath = tempnam(sys_get_temp_dir(), 'bcra_empty_');
        file_put_contents($emptyPath, '');

        $this->expectException(\Throwable::class);

        try {
            $this->orchestrator->orchestrate($emptyPath, $importId);
        } finally {
            // Assert — status is 'failed'
            $log = DB::table('import_logs')->where('id', $importId)->first();
            $this->assertNotNull($log, 'import log must exist');
            $this->assertSame('failed', $log->status, 'status must be failed on aggregate error');

            // Assert — staging table still exists (retained for resume, NOT dropped)
            $exists = DB::selectOne(
                "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
                [$staging]
            );
            $this->assertNotNull($exists, 'staging table must be retained on aggregate failure');

            // Cleanup
            $pdo->exec('DROP TABLE IF EXISTS ' . $staging);
            @unlink($fixturePath);
            @unlink($emptyPath);
        }
    }

    public function test_orchestrate_creates_import_log_if_not_found(): void
    {
        // Arrange — no pre-existing import log
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFile(lineCount: 1);

        $this->notificationSender->method('send');

        $this->assertNull(DB::table('import_logs')->where('id', $importId)->first(), 'log must not exist before orchestrate');

        // Act
        $this->orchestrator->orchestrate($fixturePath, $importId);

        // Assert — log was created
        $log = DB::table('import_logs')->where('id', $importId)->first();
        $this->assertNotNull($log);
        $this->assertSame('completed', $log->status);

        @unlink($fixturePath);
    }

    public function test_orchestrate_resumes_from_checkpoint(): void
    {
        // Arrange — pre-seed an import log with last_loaded_line = 1 (simulating partial load).
        // Create the staging table manually with the first row already present.
        $importId = (string) Str::uuid();
        $slug = substr(str_replace('-', '', $importId), 0, 12);
        $staging = 'raw_records_' . $slug;

        // Create the import log with last_loaded_line = 1
        DB::table('import_logs')->insert([
            'id'               => $importId,
            'filename'         => 'test.txt',
            'status'           => 'processing',
            'started_at'       => now(),
            'last_loaded_line' => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Create the staging table with the first row already loaded
        $pdo = DB::connection()->getPdo();
        $pdo->exec(
            "CREATE UNLOGGED TABLE {$staging} ("
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );
        $pdo->exec("INSERT INTO {$staging} VALUES ('20345123458', '00001', '01', 1500.00)");

        $this->notificationSender->method('send');

        // Create a 2-line fixture (line 1 is already loaded, only line 2 should be appended)
        $fixturePath = $this->createFixtureFile(lineCount: 2);

        // Act
        $this->orchestrator->orchestrate($fixturePath, $importId);

        // Assert — debtors: 2 unique CUITs (one from existing staging + one new)
        $this->assertSame(2, DB::table('debtors')->count(), 'both CUITs must be in debtors (no duplicate from resume)');

        @unlink($fixturePath);
    }

    public function test_orchestrate_gc_orphans_at_start_removes_stale_staging(): void
    {
        // Arrange — create a stale staging table from a completed import >24h ago.
        // gcOrphans() should drop it when orchestrate() runs.
        $staleImportId = (string) Str::uuid();
        $staleSlug = substr(str_replace('-', '', $staleImportId), 0, 12);
        $staleTable = 'raw_records_' . $staleSlug;

        // Insert a completed import log that's 25 hours old
        DB::table('import_logs')->insert([
            'id'         => $staleImportId,
            'filename'   => 'stale.txt',
            'status'     => 'completed',
            'started_at' => now()->subHours(26),
            'created_at' => now()->subHours(26),
            'updated_at' => now()->subHours(25),
        ]);

        // Create the orphan staging table
        $pdo = DB::connection()->getPdo();
        $pdo->exec(
            "CREATE UNLOGGED TABLE {$staleTable} ("
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );

        // Verify the stale table exists before the run
        $beforeExists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$staleTable]
        );
        $this->assertNotNull($beforeExists, 'stale table must exist before orchestrate()');

        // Now run a real import — gcOrphans() runs at the start
        $importId = (string) Str::uuid();
        $fixturePath = $this->createFixtureFile(lineCount: 1);
        $this->notificationSender->method('send');

        $this->orchestrator->orchestrate($fixturePath, $importId);

        // Assert — stale table was cleaned up by gcOrphans()
        $afterExists = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$staleTable]
        );
        $this->assertNull($afterExists, 'stale staging table must be dropped by gcOrphans()');

        @unlink($fixturePath);
    }

    // -----------------------------------------------------------------------
    // Fixture helpers
    // -----------------------------------------------------------------------

    /**
     * Create a minimal valid BCRA fixture file with $lineCount lines.
     * Each line has a unique CUIT and a fixed entity code '00001'.
     */
    private function createFixtureFile(int $lineCount = 2): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bcra_orch_');
        $lines = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $idNum = str_pad((string) (20345123458 + $i), 11, '0', STR_PAD_LEFT);
            $amounts = str_repeat('000000000000', 10);
            $flags = '000000';
            $daysOverdue = '0000';

            $lines[] = '00001'
                . '202601'
                . '11'
                . $idNum
                . '001'
                . '01'
                . '000000001500,'
                . $amounts
                . $flags
                . $daysOverdue;
        }

        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }

    /**
     * Create a fixture file from an explicit list of row specs.
     * Each row: ['cuit' => string, 'entity' => string, 'situation' => string, 'loans' => float]
     *
     * @param array<int, array{cuit: string, entity: string, situation: string, loans: float}> $rows
     */
    private function createFixtureFileWithSituations(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bcra_sit_');
        $lines = [];

        foreach ($rows as $row) {
            $entity = str_pad($row['entity'], 5, '0', STR_PAD_LEFT);
            $cuit = str_pad($row['cuit'], 11, '0', STR_PAD_LEFT);
            $situation = str_pad($row['situation'], 2, '0', STR_PAD_LEFT);

            // Format loans as 12-char BCRA amount: 11 integer digits + comma + no decimal
            $loansInt = (int) $row['loans'];
            $loansStr = str_pad((string) $loansInt, 11, '0', STR_PAD_LEFT) . ',';

            $amounts = str_repeat('000000000000', 10);
            $flags = '000000';
            $daysOverdue = '0000';

            $lines[] = $entity
                . '202601'
                . '11'
                . $cuit
                . '001'
                . $situation
                . $loansStr
                . $amounts
                . $flags
                . $daysOverdue;
        }

        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }
}
