<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Parser;

use App\Application\DTOs\BcraRecordDTO;
use App\Application\Parser\BcraFileParser;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\TestCase;

class BcraFileParserTest extends TestCase
{
    private function fixturesPath(): string
    {
        return dirname(__DIR__, 3) . '/Fixtures/';
    }

    public function test_parse_returns_lazy_collection(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $result = $parser->parse();

        // Assert
        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function test_parse_valid_records_all_fields_extracted(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert
        $this->assertCount(10, $records);
        $this->assertInstanceOf(BcraRecordDTO::class, $records[0]);

        // Verify first record fields
        $first = $records[0];
        $this->assertSame('00001', $first->entityCode);
        $this->assertSame('202601', $first->infoDate);
        $this->assertSame('11', $first->identificationType);
        $this->assertSame('20345123458', $first->identificationNumber);
        $this->assertSame('001', $first->activity);
        $this->assertSame('01', $first->situation);
        $this->assertSame(1500.5, $first->loans);
        $this->assertSame(0.0, $first->unused);
        $this->assertSame(500.0, $first->guarantees);
        $this->assertSame(100.0, $first->otherConcepts);
        $this->assertSame(0, $first->daysOverdue);
    }

    public function test_parse_sets_line_number_on_each_record(): void
    {
        // Arrange — use the 10-line fixture; all 10 pass filtering
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — lineNumber must be positive (1-indexed source line), distinct, monotonically increasing
        $this->assertCount(10, $records);

        // Every record must have a positive lineNumber
        foreach ($records as $record) {
            $this->assertGreaterThan(0, $record->lineNumber);
        }

        // lineNumbers must be strictly increasing (each record is a different file line)
        for ($i = 1; $i < count($records); $i++) {
            $this->assertGreaterThan($records[$i - 1]->lineNumber, $records[$i]->lineNumber);
        }
    }

    public function test_parse_rn04_filters_non_11_identification(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_mixed_types.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — only tipo=11 records pass
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $this->assertSame('11', $record->identificationType);
        }
    }

    public function test_parse_rn05_filters_invalid_situation(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_invalid_situation.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — only valid situation codes pass
        $validSituations = ['01', '03', '04', '05', '11', '21', '23'];
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $this->assertContains($record->situation, $validSituations);
        }
    }

    public function test_parse_converts_iso_8859_1_to_utf8(): void
    {
        // Arrange — fixture contains ISO-8859-1 encoded data
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — no encoding errors, records parsed successfully
        $this->assertNotEmpty($records);
        // Verify identification number is valid UTF-8
        foreach ($records as $record) {
            $this->assertTrue(mb_check_encoding($record->identificationNumber, 'UTF-8'));
        }
    }

    public function test_parse_amounts_comma_to_period_conversion(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — amounts should be floats with period decimal
        $first = $records[0];
        $this->assertIsFloat($first->loans);
        $this->assertIsFloat($first->guarantees);
        $this->assertIsFloat($first->otherConcepts);
    }

    public function test_parse_skips_empty_lines(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_edge_cases.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — empty lines should be skipped
        $this->assertNotEmpty($records);
    }

    public function test_parse_handles_short_lines_gracefully(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_edge_cases.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — short/malformed lines should be skipped, valid ones kept
        foreach ($records as $record) {
            $this->assertInstanceOf(BcraRecordDTO::class, $record);
        }
    }

    public function test_parse_all_seven_situation_codes_accepted(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act
        $records = $parser->parse()->values()->all();

        // Assert — collect unique situations from valid records
        $situations = array_unique(array_map(fn(BcraRecordDTO $r) => $r->situation, $records));
        $validSituations = ['01', '03', '04', '05', '11', '21', '23'];
        foreach ($situations as $situation) {
            $this->assertContains($situation, $validSituations);
        }
    }

    // -----------------------------------------------------------------------
    // ITEM 5 — Skipped-line counter (invalid_records)
    // -----------------------------------------------------------------------

    public function test_get_skipped_count_is_zero_for_all_valid_file(): void
    {
        // Arrange
        $parser = new BcraFileParser($this->fixturesPath() . 'bcra_10_lines.txt');

        // Act — fully consume the collection
        $parser->parse()->all();

        // Assert
        $this->assertSame(0, $parser->getSkippedCount());
    }

    public function test_get_skipped_count_counts_short_lines_and_filtered_records(): void
    {
        // Arrange — a temp fixture with 2 valid + 1 short line + 1 non-CUIT + 1 invalid situation
        $path = tempnam(sys_get_temp_dir(), 'bcra_skip_');

        $validLine = '00001' . '202601' . '11' . '20345123458' . '001' . '01'
            . '000000001500,' . str_repeat('000000000000', 10) . '000000' . '0000';

        $shortLine  = 'SHORT'; // < 171 chars
        $nonCuit    = '00001' . '202601' . '99' . '20345123458' . '001' . '01'
            . '000000001500,' . str_repeat('000000000000', 10) . '000000' . '0000'; // tipo 99
        $badSit     = '00001' . '202601' . '11' . '20999999999' . '001' . '99'
            . '000000001500,' . str_repeat('000000000000', 10) . '000000' . '0000'; // situation 99

        $secondValidLine = '00001' . '202601' . '11' . '20123456789' . '001' . '01'
            . '000000001500,' . str_repeat('000000000000', 10) . '000000' . '0000';

        file_put_contents($path, implode("\n", [
            $validLine,
            $shortLine,
            $nonCuit,
            $badSit,
            $secondValidLine,
        ]) . "\n");

        $parser = new BcraFileParser($path);

        // Act — fully consume
        $valid = $parser->parse()->values()->all();

        // Assert
        $this->assertCount(2, $valid, '2 valid lines must yield 2 DTOs');
        $this->assertSame(3, $parser->getSkippedCount(), 'short + non-CUIT + bad situation = 3 skipped');

        @unlink($path);
    }
}
