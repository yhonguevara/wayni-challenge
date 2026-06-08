<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Amount;
use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{
    public function test_from_bcra_format_valid_amount(): void
    {
        // Arrange
        $bcraAmount = '000000011,1';

        // Act
        $amount = Amount::fromBcraFormat($bcraAmount);

        // Assert
        $this->assertSame(11.1, $amount->toFloat());
    }

    public function test_from_bcra_format_comma_to_period_conversion(): void
    {
        // Arrange
        $bcraAmount = '12345678901,5';

        // Act
        $amount = Amount::fromBcraFormat($bcraAmount);

        // Assert
        $this->assertSame(12345678901.5, $amount->toFloat());
    }

    public function test_from_bcra_format_zero(): void
    {
        // Arrange
        $bcraAmount = '00000000000,0';

        // Act
        $amount = Amount::fromBcraFormat($bcraAmount);

        // Assert
        $this->assertSame(0.0, $amount->toFloat());
    }

    public function test_from_bcra_format_empty_string(): void
    {
        // Arrange
        $bcraAmount = '';

        // Act
        $amount = Amount::fromBcraFormat($bcraAmount);

        // Assert
        $this->assertSame(0.0, $amount->toFloat());
    }

    public function test_from_bcra_format_large_amount(): void
    {
        // Arrange
        $bcraAmount = '99999999999,9';

        // Act
        $amount = Amount::fromBcraFormat($bcraAmount);

        // Assert
        $this->assertSame(99999999999.9, $amount->toFloat());
    }

    public function test_add_two_amounts(): void
    {
        // Arrange
        $amount1 = new Amount(10.5);
        $amount2 = new Amount(20.3);

        // Act
        $result = $amount1->add($amount2);

        // Assert
        $this->assertSame(30.8, $result->toFloat());
    }

    public function test_add_returns_new_instance(): void
    {
        // Arrange
        $amount1 = new Amount(10.0);
        $amount2 = new Amount(20.0);

        // Act
        $result = $amount1->add($amount2);

        // Assert
        $this->assertNotSame($amount1, $result);
        $this->assertNotSame($amount2, $result);
        $this->assertSame(10.0, $amount1->toFloat());
        $this->assertSame(20.0, $amount2->toFloat());
    }

    public function test_add_zero(): void
    {
        // Arrange
        $amount1 = new Amount(10.5);
        $amount2 = new Amount(0.0);

        // Act
        $result = $amount1->add($amount2);

        // Assert
        $this->assertSame(10.5, $result->toFloat());
    }

    public function test_to_float_returns_float(): void
    {
        // Arrange
        $amount = new Amount(123.45);

        // Act
        $result = $amount->toFloat();

        // Assert
        $this->assertIsFloat($result);
        $this->assertSame(123.45, $result);
    }
}
