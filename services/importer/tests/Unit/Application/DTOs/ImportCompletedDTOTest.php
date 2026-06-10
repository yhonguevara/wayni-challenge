<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\ImportCompletedDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImportCompletedDTO.
 *
 * Verifies constructor property assignment and fromArray deserialization
 * matching the camelCase SQS wire contract published by ImportCompleted domain event.
 */
class ImportCompletedDTOTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $dto = new ImportCompletedDTO(
            importId: 'import-abc-123',
            totalDebtors: 500,
            totalEntities: 20,
        );

        $this->assertSame('import-abc-123', $dto->importId);
        $this->assertSame(500, $dto->totalDebtors);
        $this->assertSame(20, $dto->totalEntities);
    }

    public function test_from_array_maps_camel_case_wire_format(): void
    {
        $payload = [
            'importId'      => 'import-xyz-999',
            'totalDebtors'  => 150,
            'totalEntities' => 5,
        ];

        $dto = ImportCompletedDTO::fromArray($payload);

        $this->assertSame('import-xyz-999', $dto->importId);
        $this->assertSame(150, $dto->totalDebtors);
        $this->assertSame(5, $dto->totalEntities);
    }

    public function test_from_array_casts_integer_fields(): void
    {
        $dto = ImportCompletedDTO::fromArray([
            'importId'      => 'import-001',
            'totalDebtors'  => '300',
            'totalEntities' => '12',
        ]);

        $this->assertIsInt($dto->totalDebtors);
        $this->assertIsInt($dto->totalEntities);
    }
}
