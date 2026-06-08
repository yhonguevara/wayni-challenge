<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\EntityRecord;
use App\Domain\ValueObjects\Amount;
use PHPUnit\Framework\TestCase;

class EntityRecordTest extends TestCase
{
    public function test_construction_with_entity_code_and_amount(): void
    {
        // Arrange
        $entityCode = '00001';
        $amount = new Amount(1500.5);

        // Act
        $record = new EntityRecord(
            entityCode: $entityCode,
            totalLoans: $amount,
        );

        // Assert
        $this->assertSame('00001', $record->entityCode);
        $this->assertSame(1500.5, $record->totalLoans->toFloat());
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $record = new EntityRecord(
            entityCode: '00001',
            totalLoans: new Amount(1500.5),
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertSame('00001', $record->entityCode);
        $this->assertInstanceOf(Amount::class, $record->totalLoans);
    }
}
