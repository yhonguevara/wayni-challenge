<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Amount;
use App\Domain\ValueObjects\Cuit;
use App\Domain\ValueObjects\Situation;

final readonly class DebtorRecord
{
    public function __construct(
        public Cuit $identificationNumber,
        public Situation $situation,
        public Amount $loansAmount,
        public string $entityCode,
    ) {}
}
