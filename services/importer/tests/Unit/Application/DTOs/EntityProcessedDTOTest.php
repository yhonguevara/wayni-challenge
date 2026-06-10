<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\EntityProcessedDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntityProcessedDTO.
 *
 * Verifies constructor property assignment and fromArray deserialization
 * matching the camelCase SQS wire contract.
 */
class EntityProcessedDTOTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $dto = new EntityProcessedDTO(
            importId: 'import-abc-123',
            entityCode: 'BCO01',
            totalLoans: 50000.00,
        );

        $this->assertSame('import-abc-123', $dto->importId);
        $this->assertSame('BCO01', $dto->entityCode);
        $this->assertSame(50000.00, $dto->totalLoans);
    }

    public function test_from_array_maps_camel_case_wire_format(): void
    {
        $payload = [
            'importId'   => 'import-xyz-999',
            'entityCode' => '00042',
            'totalLoans' => 75000.00,
        ];

        $dto = EntityProcessedDTO::fromArray($payload);

        $this->assertSame('import-xyz-999', $dto->importId);
        $this->assertSame('00042', $dto->entityCode);
        $this->assertSame(75000.00, $dto->totalLoans);
    }

    public function test_from_array_casts_total_loans_to_float(): void
    {
        $dto = EntityProcessedDTO::fromArray([
            'importId'   => 'import-001',
            'entityCode' => 'BCO01',
            'totalLoans' => '12345.67',
        ]);

        $this->assertIsFloat($dto->totalLoans);
        $this->assertSame(12345.67, $dto->totalLoans);
    }
}
