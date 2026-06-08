<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

enum Situation: string
{
    case Normal = '01';
    case LowRisk = '21';
    case SpecialTreatment = '23';
    case MediumRisk = '03';
    case HighRisk = '04';
    case Unrecoverable = '05';
    case CoveredAssistance = '11';

    /**
     * Returns severity level (0-6, higher = worse).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Normal => 0,
            self::CoveredAssistance => 1,
            self::LowRisk => 2,
            self::SpecialTreatment => 3,
            self::MediumRisk => 4,
            self::HighRisk => 5,
            self::Unrecoverable => 6,
        };
    }

    /**
     * Check if this situation is worse than another.
     */
    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    /**
     * Return the worst possible situation (highest severity).
     */
    public static function worst(): self
    {
        return self::Unrecoverable;
    }

    /**
     * Return all valid situation codes.
     *
     * @return array<string>
     */
    public static function validCodes(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
