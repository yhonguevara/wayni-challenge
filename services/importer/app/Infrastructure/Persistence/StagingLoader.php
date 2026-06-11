<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTOs\BcraRecordDTO;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * StagingLoader: per-import COPY-based bulk loader with checkpoint/resume.
 *
 * Encapsulates:
 * - Creating/recreating a per-import UNLOGGED staging table (raw_records_<slug>).
 * - Streaming BcraRecordDTOs into the staging table via PDO::pgsqlCopyFromArray
 *   in chunks of CHUNK_SIZE rows (memory bounded).
 * - Writing a checkpoint (last_loaded_line) atomically with each chunk flush.
 * - Resume: when last_loaded_line > 0 and staging table exists, skip rows
 *   already loaded (line number <= last_loaded_line) and append from the remainder.
 * - GC: drop raw_records_* tables whose import_log is terminal and older than 24 h.
 */
final class StagingLoader
{
    /** Number of rows per COPY flush. */
    private const CHUNK_SIZE = 5_000;

    /** Hours after terminal status before a staging table is considered stale. */
    private const GC_TTL_HOURS = 24;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Load an iterable of BcraRecordDTOs into the staging table for the given import.
     *
     * Algorithm:
     * 1. Read last_loaded_line from import_logs.
     * 2. If last_loaded_line === 0 OR staging table does not exist: DROP + CREATE UNLOGGED.
     * 3. Stream records; skip those with lineNumber <= last_loaded_line (resume skip).
     * 4. Accumulate into a buffer; when buffer reaches CHUNK_SIZE, flush atomically
     *    (COPY + UPDATE last_loaded_line) in a single DB transaction.
     * 5. Flush remaining buffer at end.
     *
     * @param iterable<BcraRecordDTO> $records
     */
    public function load(string $importId, iterable $records): void
    {
        $slug = $this->slugFromId($importId);
        $table = 'raw_records_' . $slug;

        $lastLine = $this->fetchLastLoadedLine($importId);

        if ($lastLine === 0 || ! $this->stagingTableExists($table)) {
            $this->dropAndCreate($table);
            $lastLine = 0;
        }

        $buffer = [];
        $currentLine = $lastLine;

        foreach ($records as $record) {
            // Skip rows that were already loaded in a previous run.
            if ($record->lineNumber <= $lastLine) {
                continue;
            }

            $buffer[] = $this->formatCopyRow($record);
            $currentLine = $record->lineNumber;

            if (count($buffer) === self::CHUNK_SIZE) {
                $this->flushChunk($importId, $table, $buffer, $currentLine);
                $buffer = [];
            }
        }

        // Flush any remaining rows in the final partial chunk.
        if ($buffer !== []) {
            $this->flushChunk($importId, $table, $buffer, $currentLine);
        }
    }

    /**
     * Drop the staging table for the given import, if it exists.
     */
    public function dropStaging(string $importId): void
    {
        $slug = $this->slugFromId($importId);
        $this->pdo->exec('DROP TABLE IF EXISTS raw_records_' . $slug);
    }

    /**
     * Drop stale raw_records_* tables: those whose import_log is in a terminal
     * status (completed or failed) and was last updated more than GC_TTL_HOURS ago.
     */
    public function gcOrphans(): void
    {
        // Find all raw_records_* tables in the public schema.
        $rows = DB::select(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = 'public' AND table_name LIKE 'raw_records_%'"
        );

        foreach ($rows as $row) {
            $tableName = $row->table_name;
            $slug = substr($tableName, strlen('raw_records_'));

            // Match this slug to an import_log: slug = first 12 hex chars of UUID (dashes stripped).
            $log = DB::table('import_logs')
                ->whereRaw("SUBSTR(REPLACE(id::text, '-', ''), 1, 12) = ?", [$slug])
                ->first();

            if ($log === null) {
                // Orphan with no matching import_log — drop it.
                $this->pdo->exec('DROP TABLE IF EXISTS ' . $tableName);
                continue;
            }

            $isTerminal = in_array($log->status, ['completed', 'failed'], strict: true);
            $updatedAt = \Carbon\Carbon::parse($log->updated_at);
            $isStale = $updatedAt->diffInHours(now()) >= self::GC_TTL_HOURS;

            if ($isTerminal && $isStale) {
                $this->pdo->exec('DROP TABLE IF EXISTS ' . $tableName);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Compute the staging table slug from an import UUID.
     *
     * slug = first 12 lowercase hex characters of the UUID with dashes stripped.
     * Example: 'aabbccdd-1122-3344-...' → 'aabbccdd1122'
     */
    private function slugFromId(string $importId): string
    {
        return substr(str_replace('-', '', $importId), 0, 12);
    }

    /**
     * Read last_loaded_line from import_logs for the given import.
     */
    private function fetchLastLoadedLine(string $importId): int
    {
        $row = DB::table('import_logs')
            ->where('id', $importId)
            ->value('last_loaded_line');

        return (int) ($row ?? 0);
    }

    /**
     * Check whether the staging table exists in the public schema.
     */
    private function stagingTableExists(string $table): bool
    {
        $row = DB::selectOne(
            "SELECT 1 AS found FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$table]
        );

        return $row !== null;
    }

    /**
     * Drop the staging table (if it exists) and recreate it as UNLOGGED.
     */
    private function dropAndCreate(string $table): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
        $this->pdo->exec(
            'CREATE UNLOGGED TABLE ' . $table . ' ('
            . 'identification_number VARCHAR(11) NOT NULL,'
            . 'entity_code VARCHAR(5) NOT NULL,'
            . 'situation VARCHAR(2) NOT NULL,'
            . 'loans NUMERIC(18,2) NOT NULL'
            . ')'
        );
    }

    /**
     * Format a BcraRecordDTO as a tab-delimited COPY line.
     *
     * Escapes tab (\t), newline (\n), and backslash (\\) in string values
     * per PostgreSQL COPY text-format rules.
     *
     * BCRA source data is fixed-width ASCII/ISO-8859-1 with no embedded tabs
     * or newlines, but we escape defensively.
     */
    private function formatCopyRow(BcraRecordDTO $record): string
    {
        return implode("\t", [
            $this->escapeCopyValue($record->identificationNumber),
            $this->escapeCopyValue($record->entityCode),
            $this->escapeCopyValue($record->situation),
            number_format($record->loans, 2, '.', ''),
        ]);
    }

    /**
     * Escape a string value for use in a PostgreSQL COPY text-format row.
     */
    private function escapeCopyValue(string $value): string
    {
        return str_replace(
            ['\\', "\t", "\n"],
            ['\\\\', '\\t', '\\n'],
            $value
        );
    }

    /**
     * Flush $buffer rows to the staging table and update the checkpoint,
     * all within a single database transaction.
     *
     * Uses DB::transaction() (Laravel's transactional wrapper) rather than
     * raw PDO::beginTransaction() so that nested calls during tests — which
     * RefreshDatabase wraps in an outer transaction — are handled correctly
     * via PostgreSQL savepoints.
     *
     * Note: pgsqlCopyFromArray runs on the same underlying PDO connection that
     * Laravel uses, so it participates in the same transaction / savepoint block.
     *
     * @param list<string> $buffer Tab-delimited COPY lines.
     */
    private function flushChunk(
        string $importId,
        string $table,
        array $buffer,
        int $lastLineInChunk,
    ): void {
        DB::transaction(function () use ($importId, $table, $buffer, $lastLineInChunk): void {
            $columns = 'identification_number,entity_code,situation,loans';
            $result = $this->pdo->pgsqlCopyFromArray($table, $buffer, "\t", '\\N', $columns);

            if ($result === false) {
                throw new \RuntimeException(
                    'pgsqlCopyFromArray returned false for table ' . $table
                );
            }

            // Update checkpoint in the same transaction — atomic with the COPY.
            DB::table('import_logs')
                ->where('id', $importId)
                ->update(['last_loaded_line' => $lastLineInChunk]);
        });
    }
}
