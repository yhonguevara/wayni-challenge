<?php

declare(strict_types=1);

namespace App\Application\Parser;

use App\Application\DTOs\BcraRecordDTO;
use App\Domain\ValueObjects\Situation;
use Illuminate\Support\LazyCollection;
use SplFileObject;

/**
 * Streaming parser for BCRA deudores.txt files.
 *
 * Parses 171-character fixed-width lines, extracts 24 fields per position,
 * filters by identification type (RN-03) and valid situation codes (RN-04).
 * Returns a LazyCollection for memory-efficient streaming.
 */
final class BcraFileParser
{
    private const LINE_LENGTH = 171;

    /** Identification type for CUIT/CUIL/CDI (RN-03) */
    private const IDENTIFICATION_TYPE_CUIT = '11';

    public function __construct(
        private readonly string $filePath,
    ) {}

    /**
     * Parse the BCRA file and return a lazy collection of BcraRecordDTO.
     *
     * @return LazyCollection<int, BcraRecordDTO>
     */
    public function parse(): LazyCollection
    {
        return LazyCollection::make(function (): \Generator {
            $file = new SplFileObject($this->filePath);
            $file->setFlags(
                SplFileObject::DROP_NEW_LINE
                | SplFileObject::SKIP_EMPTY
                | SplFileObject::READ_AHEAD
            );

            $lineNumber = 0;

            while (!$file->eof()) {
                $line = $file->fgets();
                $lineNumber++;

                // Skip empty lines
                if ($line === '' || $line === false) {
                    continue;
                }

                // Skip comment lines (fixtures may have header comments)
                if (str_starts_with($line, '#')) {
                    continue;
                }

                // Convert ISO-8859-1 → UTF-8 (RN-07)
                $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');

                // Validate line length
                if (mb_strlen($line) < self::LINE_LENGTH) {
                    // Log warning and skip malformed lines
                    error_log(sprintf(
                        'BCRA Parser: Skipping line %d (length %d, expected %d)',
                        $lineNumber,
                        mb_strlen($line),
                        self::LINE_LENGTH,
                    ));
                    continue;
                }

                try {
                    $dto = $this->parseLine($line);

                    // RN-03: Filter non-CUIT identification types
                    if ($dto->identificationType !== self::IDENTIFICATION_TYPE_CUIT) {
                        continue;
                    }

                    // RN-04: Filter invalid situation codes
                    if (!in_array($dto->situation, Situation::validCodes(), true)) {
                        continue;
                    }

                    yield $dto;
                } catch (\Throwable $e) {
                    error_log(sprintf(
                        'BCRA Parser: Error parsing line %d: %s',
                        $lineNumber,
                        $e->getMessage(),
                    ));
                    continue;
                }
            }
        });
    }

    /**
     * Parse a single 171-character BCRA line into a BcraRecordDTO.
     *
     * Field positions are 0-indexed for substr(), matching leame-deudores.md
     * positions minus 1 (doc is 1-indexed).
     */
    private function parseLine(string $line): BcraRecordDTO
    {
        return new BcraRecordDTO(
            entityCode: substr($line, 0, 5),           // pos 1-5
            infoDate: substr($line, 5, 6),              // pos 6-11
            identificationType: substr($line, 11, 2),   // pos 12-13
            identificationNumber: trim(substr($line, 13, 11)), // pos 14-24 (CHARACTER, trim spaces)
            activity: substr($line, 24, 3),             // pos 25-27
            situation: $this->normalizeSituation(substr($line, 27, 2)), // pos 28-29
            loans: $this->parseAmount(substr($line, 29, 12)),   // pos 30-41
            unused: $this->parseAmount(substr($line, 41, 12)),  // pos 42-53
            guarantees: $this->parseAmount(substr($line, 53, 12)),  // pos 54-65
            otherConcepts: $this->parseAmount(substr($line, 65, 12)), // pos 66-77
            preferredGuaranteesA: $this->parseAmount(substr($line, 77, 12)),  // pos 78-89
            preferredGuaranteesB: $this->parseAmount(substr($line, 89, 12)),  // pos 90-101
            noPreferredGuarantees: $this->parseAmount(substr($line, 101, 12)), // pos 102-113
            counterGuaranteesA: $this->parseAmount(substr($line, 113, 12)),   // pos 114-125
            counterGuaranteesB: $this->parseAmount(substr($line, 125, 12)),   // pos 126-137
            noCounterGuarantees: $this->parseAmount(substr($line, 137, 12)),  // pos 138-149
            provisions: $this->parseAmount(substr($line, 149, 12)),           // pos 150-161
            debtCovered: substr($line, 161, 1),         // pos 162
            judicialProcess: substr($line, 162, 1),     // pos 163
            refinancing: substr($line, 163, 1),         // pos 164
            mandatoryRecat: substr($line, 164, 1),      // pos 165
            legalSituation: substr($line, 165, 1),      // pos 166
            technicalIrrecoverable: substr($line, 166, 1), // pos 167
            daysOverdue: (int) substr($line, 167, 4),   // pos 168-171
        );
    }

    /**
     * Normalize the situation code to the canonical 2-digit form.
     *
     * The official spec defines Campo 6 as 2 numeric chars (01, 03, 04, 05, 11,
     * 21, 23), but legacy BCRA files (e.g. nov-2023) left-align a single digit
     * with a trailing space ("1 ", "5 "). We trim the padding and zero-pad to 2,
     * so "1 " → "01", "5 " → "05". This keeps the canonical validCodes() check
     * working against both the new and legacy file formats.
     */
    private function normalizeSituation(string $raw): string
    {
        return str_pad(trim($raw), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Parse BCRA amount format: "000000011,1" → 11.1
     *
     * Format: 11 integers + 1 decimal, comma separator.
     * We strip leading zeros, replace comma with period, then convert to float.
     */
    private function parseAmount(string $raw): float
    {
        // Remove leading zeros, keep at least one digit
        $trimmed = ltrim($raw, '0');
        if ($trimmed === '' || $trimmed[0] === ',') {
            $trimmed = '0' . $trimmed;
        }

        // Replace comma with period for float conversion
        return (float) str_replace(',', '.', $trimmed);
    }
}
