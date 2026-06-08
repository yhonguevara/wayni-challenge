<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\IdentificationType;
use PHPUnit\Framework\TestCase;

class IdentificationTypeTest extends TestCase
{
    public function test_from_code_valid_cuit(): void
    {
        // Arrange
        $code = '11';

        // Act
        $type = IdentificationType::from($code);

        // Assert
        $this->assertSame(IdentificationType::Cuit, $type);
    }

    public function test_from_code_invalid_code_throws_value_error(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\ValueError::class);

        IdentificationType::from('05');
    }

    public function test_from_code_invalid_code_80_throws_value_error(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\ValueError::class);

        IdentificationType::from('80');
    }

    public function test_cuit_value_is_11(): void
    {
        // Arrange & Act
        $type = IdentificationType::Cuit;

        // Assert
        $this->assertSame('11', $type->value);
    }
}
