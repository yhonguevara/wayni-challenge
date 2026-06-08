<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Cuit;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CuitTest extends TestCase
{
    public function test_from_string_valid_cuit(): void
    {
        // Arrange
        $cuitString = '20345123458';

        // Act
        $cuit = Cuit::fromString($cuitString);

        // Assert
        $this->assertSame('20345123458', $cuit->value());
    }

    public function test_from_string_trims_whitespace(): void
    {
        // Arrange
        $cuitString = '20345123458   ';

        // Act
        $cuit = Cuit::fromString($cuitString);

        // Assert
        $this->assertSame('20345123458', $cuit->value());
    }

    public function test_from_string_trims_leading_whitespace(): void
    {
        // Arrange
        $cuitString = '   20345123458';

        // Act
        $cuit = Cuit::fromString($cuitString);

        // Assert
        $this->assertSame('20345123458', $cuit->value());
    }

    public function test_from_string_empty_string_throws_exception(): void
    {
        // Arrange & Act & Assert
        $this->expectException(InvalidArgumentException::class);

        Cuit::fromString('');
    }

    public function test_from_string_whitespace_only_throws_exception(): void
    {
        // Arrange & Act & Assert
        $this->expectException(InvalidArgumentException::class);

        Cuit::fromString('   ');
    }

    public function test_from_string_too_short_throws_exception(): void
    {
        // Arrange & Act & Assert
        $this->expectException(InvalidArgumentException::class);

        Cuit::fromString('1234567890');
    }

    public function test_from_string_too_long_throws_exception(): void
    {
        // Arrange & Act & Assert
        $this->expectException(InvalidArgumentException::class);

        Cuit::fromString('123456789012');
    }

    public function test_from_string_non_numeric_throws_exception(): void
    {
        // Arrange & Act & Assert
        $this->expectException(InvalidArgumentException::class);

        Cuit::fromString('203451234AB');
    }

    public function test_equals_same_cuit(): void
    {
        // Arrange
        $cuit1 = Cuit::fromString('20345123458');
        $cuit2 = Cuit::fromString('20345123458');

        // Act & Assert
        $this->assertTrue($cuit1->equals($cuit2));
    }

    public function test_equals_different_cuit(): void
    {
        // Arrange
        $cuit1 = Cuit::fromString('20345123458');
        $cuit2 = Cuit::fromString('20345123459');

        // Act & Assert
        $this->assertFalse($cuit1->equals($cuit2));
    }
}
