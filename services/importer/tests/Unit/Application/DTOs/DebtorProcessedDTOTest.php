<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\DebtorProcessedDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DebtorProcessedDTO.
 *
 * Verifies constructor property assignment and fromArray deserialization
 * matching the camelCase SQS wire contract.
 */
class DebtorProcessedDTOTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        // Arrange + Act
        $dto = new DebtorProcessedDTO(
            importId: 'import-abc-123',
            identificationNumber: '20345123458',
            maxSituation: '03',
            totalLoans: 1500.75,
            lineNumber: 42,
        );

        // Assert
        $this->assertSame('import-abc-123', $dto->importId);
        $this->assertSame('20345123458', $dto->identificationNumber);
        $this->assertSame('03', $dto->maxSituation);
        $this->assertSame(1500.75, $dto->totalLoans);
        $this->assertSame(42, $dto->lineNumber);
    }

    public function test_from_array_maps_camel_case_wire_format(): void
    {
        // Arrange
        $payload = [
            'importId'             => 'import-xyz-999',
            'identificationNumber' => '27123456789',
            'maxSituation'         => '05',
            'totalLoans'           => 2500.00,
            'lineNumber'           => 77,
        ];

        // Act
        $dto = DebtorProcessedDTO::fromArray($payload);

        // Assert
        $this->assertSame('import-xyz-999', $dto->importId);
        $this->assertSame('27123456789', $dto->identificationNumber);
        $this->assertSame('05', $dto->maxSituation);
        $this->assertSame(2500.00, $dto->totalLoans);
        $this->assertSame(77, $dto->lineNumber);
    }

    public function test_line_number_defaults_to_zero_for_backward_compatibility(): void
    {
        // fromArray without lineNumber key (old message format) should default to 0
        $dto = DebtorProcessedDTO::fromArray([
            'importId'             => 'import-001',
            'identificationNumber' => '20345123458',
            'maxSituation'         => '01',
            'totalLoans'           => 1000.0,
        ]);

        $this->assertSame(0, $dto->lineNumber);
    }

    public function test_from_array_casts_total_loans_to_float(): void
    {
        $dto = DebtorProcessedDTO::fromArray([
            'importId'             => 'import-001',
            'identificationNumber' => '20345123458',
            'maxSituation'         => '01',
            'totalLoans'           => '3000.50',
        ]);

        $this->assertIsFloat($dto->totalLoans);
        $this->assertSame(3000.50, $dto->totalLoans);
    }
}
