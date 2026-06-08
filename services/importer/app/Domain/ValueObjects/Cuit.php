<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Cuit
{
    private const LENGTH = 11;

    public function __construct(
        private string $value,
    ) {
        if ($value === '' || ctype_space($value)) {
            throw new InvalidArgumentException('CUIT cannot be empty or whitespace only.');
        }

        if (strlen($value) !== self::LENGTH) {
            throw new InvalidArgumentException("CUIT must be exactly " . self::LENGTH . " digits, got " . strlen($value) . ".");
        }

        if (!ctype_digit($value)) {
            throw new InvalidArgumentException('CUIT must contain only numeric digits.');
        }
    }

    /**
     * Create Cuit from string, trimming whitespace.
     */
    public static function fromString(string $cuit): self
    {
        $trimmed = trim($cuit);

        return new self($trimmed);
    }

    /**
     * Get the CUIT value.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another Cuit.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
