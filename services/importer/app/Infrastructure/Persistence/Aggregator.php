<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Aggregator: runs the severity-correct GROUP BY INSERT from staging to target tables.
 *
 * aggregate() performs:
 * 1. TRUNCATE debtors and entities (latest-file-wins semantics).
 * 2. INSERT INTO debtors … SELECT … FROM raw_records_<slug>
 *    using an array-index trick to map CASE severity ranks back to situation codes.
 * 3. INSERT INTO entities … SELECT … FROM raw_records_<slug>
 *
 * Severity order (ascending): 01 < 11 < 21 < 23 < 03 < 04 < 05
 * Array is 1-indexed in PostgreSQL, ranks are 1..7:
 *   ARRAY['01','11','21','23','03','04','05'][MAX(CASE situation WHEN '01' THEN 1 … WHEN '05' THEN 7 END)]
 *
 * Rationale: plain alphabetical MAX('05','23') = '23' which is WRONG.
 * This rank-and-lookup pattern is the only correct SQL-level approach.
 */
final class Aggregator
{
    /**
     * work_mem for the aggregation connection. The GROUP BY over the full
     * staging set (tens of millions of rows) must hash-aggregate in memory
     * rather than spilling to disk. PostgreSQL accepts the quoted form.
     */
    private const AGGREGATION_WORK_MEM = "'256MB'";

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Run the aggregation for the given import.
     *
     * Derives the staging table name from the import UUID, then executes
     * TRUNCATE + INSERT for debtors and entities in sequence.
     *
     * The two INSERT statements are NOT wrapped in a single cross-table
     * transaction: aggregation is idempotent and re-runnable. A partial
     * failure (e.g. debtors inserted but entities failed) is fine — the
     * caller (ImportOrchestrator in WU-4) will mark the import as failed
     * and the staging table is retained for resume/retry.
     */
    public function aggregate(string $importId): void
    {
        $slug = $this->slugFromId($importId);
        $staging = 'raw_records_' . $slug;

        // The default work_mem (4MB) forces the GROUP BY over tens of millions
        // of staging rows to spill to disk, which dominates the runtime. Raise
        // it for this connection so the hash aggregate stays in memory. Scoped
        // to the worker connection; it does not change the server default.
        DB::statement('SET work_mem = ' . self::AGGREGATION_WORK_MEM);

        $this->aggregateDebtors($staging);
        $this->aggregateEntities($staging);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Compute the staging table slug from an import UUID.
     *
     * slug = first 12 lowercase hex characters of the UUID with dashes stripped.
     */
    private function slugFromId(string $importId): string
    {
        return substr(str_replace('-', '', $importId), 0, 12);
    }

    /**
     * TRUNCATE debtors and INSERT severity-correct rows from staging.
     *
     * The ARRAY['01','11','21','23','03','04','05'] is 1-indexed.
     * MAX(CASE … THEN rank END) picks the highest-severity rank per CUIT,
     * and the array lookup maps it back to the situation code string.
     */
    private function aggregateDebtors(string $staging): void
    {
        DB::statement('TRUNCATE debtors');

        // Drop the secondary (query-only) indexes before the bulk INSERT and
        // recreate them after. Maintaining them per-row across ~20M inserts is
        // the dominant cost; building each index once at the end is far faster.
        // The PRIMARY KEY and the identification_number UNIQUE constraint stay —
        // they guard integrity during the load.
        DB::statement('DROP INDEX IF EXISTS idx_debtors_situation');
        DB::statement('DROP INDEX IF EXISTS idx_debtors_loan_amount');

        // max_situation = the highest-severity code per CUIT. The CASE maps each
        // code to a severity rank (1..7, NOT alphabetical), MAX picks the worst,
        // and the 1-indexed array maps the rank back to the code. Only valid
        // codes reach the staging table (the parser filters by RN-04), so MAX is
        // always over a non-empty set of mapped ranks.
        DB::statement(
            "INSERT INTO debtors (identification_number, max_situation, total_loan_amount, created_at, updated_at)
             SELECT
               identification_number,
               (ARRAY['01','11','21','23','03','04','05'])[MAX(
                 CASE situation
                   WHEN '01' THEN 1
                   WHEN '11' THEN 2
                   WHEN '21' THEN 3
                   WHEN '23' THEN 4
                   WHEN '03' THEN 5
                   WHEN '04' THEN 6
                   WHEN '05' THEN 7
                 END
               )],
               SUM(loans),
               NOW(),
               NOW()
             FROM {$staging}
             GROUP BY identification_number"
        );

        DB::statement('CREATE INDEX idx_debtors_situation ON debtors (max_situation)');
        DB::statement('CREATE INDEX idx_debtors_loan_amount ON debtors (total_loan_amount)');
    }

    /**
     * TRUNCATE entities and INSERT summed loan amounts from staging.
     *
     * Entities have no situation column — only a plain SUM(loans) per entity_code.
     */
    private function aggregateEntities(string $staging): void
    {
        DB::statement('TRUNCATE entities');

        DB::statement(
            "INSERT INTO entities (entity_code, total_loan_amount, created_at, updated_at)
             SELECT
               entity_code,
               SUM(loans),
               NOW(),
               NOW()
             FROM {$staging}
             GROUP BY entity_code"
        );
    }
}
