<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class Amount
{
    public function __construct(
        private float $value,
    ) {}

    /**
     * Parse BCRA format amount string to Amount.
     * Format: "000000011,1" → 11.1 (comma → period, already in thousands).
     */
    public static function fromBcraFormat(string $raw): self
    {
        if ($raw === '') {
            return new self(0.0);
        }

        // Replace comma with period for float conversion
        $normalized = str_replace(',', '.', $raw);

        return new self((float) $normalized);
    }

    /**
     * Add another Amount (immutable - returns new instance).
     */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Get the float value.
     */
    public function toFloat(): float
    {
        return $this->value;
    }
}
