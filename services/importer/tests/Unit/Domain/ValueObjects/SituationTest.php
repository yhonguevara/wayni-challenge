<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Situation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SituationTest extends TestCase
{
    public function test_from_code_valid_situation(): void
    {
        // Arrange & Act
        $situation = Situation::from('01');

        // Assert
        $this->assertSame(Situation::Normal, $situation);
    }

    public function test_from_code_all_valid_codes(): void
    {
        // Arrange
        $validCodes = ['01', '03', '04', '05', '11', '21', '23'];

        foreach ($validCodes as $code) {
            // Act
            $situation = Situation::from($code);

            // Assert
            $this->assertSame($code, $situation->value);
        }
    }

    public function test_from_code_invalid_code_throws_value_error(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\ValueError::class);

        Situation::from('99');
    }

    public function test_from_code_invalid_code_00_throws_value_error(): void
    {
        // Arrange & Act & Assert
        $this->expectException(\ValueError::class);

        Situation::from('00');
    }

    public function test_severity_ordering_05_worse_than_04(): void
    {
        // Arrange
        $worse = Situation::from('05');
        $better = Situation::from('04');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_04_worse_than_03(): void
    {
        // Arrange
        $worse = Situation::from('04');
        $better = Situation::from('03');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_03_worse_than_23(): void
    {
        // Arrange
        $worse = Situation::from('03');
        $better = Situation::from('23');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_23_worse_than_21(): void
    {
        // Arrange
        $worse = Situation::from('23');
        $better = Situation::from('21');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_21_worse_than_11(): void
    {
        // Arrange
        $worse = Situation::from('21');
        $better = Situation::from('11');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_11_worse_than_01(): void
    {
        // Arrange
        $worse = Situation::from('11');
        $better = Situation::from('01');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_ordering_05_worse_than_01(): void
    {
        // Arrange
        $worse = Situation::from('05');
        $better = Situation::from('01');

        // Act & Assert
        $this->assertTrue($worse->isWorseThan($better));
        $this->assertFalse($better->isWorseThan($worse));
    }

    public function test_severity_same_situation_not_worse(): void
    {
        // Arrange
        $situation = Situation::from('03');

        // Act & Assert
        $this->assertFalse($situation->isWorseThan($situation));
    }

    public function test_severity_returns_integer(): void
    {
        // Arrange
        $situation = Situation::from('05');

        // Act
        $severity = $situation->severity();

        // Assert
        $this->assertIsInt($severity);
        $this->assertSame(6, $severity);
    }

    public function test_severity_values_are_ascending(): void
    {
        // Arrange
        $codes = ['01', '11', '21', '23', '03', '04', '05'];
        $expectedSeverities = [0, 1, 2, 3, 4, 5, 6];

        // Act & Assert
        foreach ($codes as $index => $code) {
            $situation = Situation::from($code);
            $this->assertSame($expectedSeverities[$index], $situation->severity(), "Severity for code {$code} should be {$expectedSeverities[$index]}");
        }
    }
}
