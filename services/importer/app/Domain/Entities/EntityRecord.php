<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Amount;

final readonly class EntityRecord
{
    public function __construct(
        public string $entityCode,
        public Amount $totalLoans,
    ) {}
}
