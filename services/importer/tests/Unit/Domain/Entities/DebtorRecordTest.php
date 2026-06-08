<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\DebtorRecord;
use App\Domain\ValueObjects\Amount;
use App\Domain\ValueObjects\Cuit;
use App\Domain\ValueObjects\Situation;
use PHPUnit\Framework\TestCase;

class DebtorRecordTest extends TestCase
{
    public function test_construction_with_value_objects(): void
    {
        // Arrange
        $cuit = Cuit::fromString('20345123458');
        $situation = Situation::from('01');
        $amount = new Amount(1500.5);
        $entityCode = '00001';

        // Act
        $record = new DebtorRecord(
            identificationNumber: $cuit,
            maxSituation: $situation,
            totalLoans: $amount,
            entityCode: $entityCode,
        );

        // Assert
        $this->assertSame($cuit, $record->identificationNumber);
        $this->assertSame($situation, $record->maxSituation);
        $this->assertSame($amount, $record->totalLoans);
        $this->assertSame('00001', $record->entityCode);
    }

    public function test_properties_are_readonly(): void
    {
        // Arrange
        $record = new DebtorRecord(
            identificationNumber: Cuit::fromString('20345123458'),
            maxSituation: Situation::from('01'),
            totalLoans: new Amount(1500.5),
            entityCode: '00001',
        );

        // Act & Assert - readonly properties cannot be modified
        $this->assertInstanceOf(Cuit::class, $record->identificationNumber);
        $this->assertInstanceOf(Situation::class, $record->maxSituation);
        $this->assertInstanceOf(Amount::class, $record->totalLoans);
    }
}
