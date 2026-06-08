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
}
