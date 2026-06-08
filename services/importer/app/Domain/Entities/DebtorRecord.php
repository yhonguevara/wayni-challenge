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
        public Situation $maxSituation,
        public Amount $totalLoans,
        /**
         * Entity code used for aggregation during transformation.
         * Not part of the domain spec but needed by BcraDataTransformer
         * to associate this debtor record with its originating entity.
         */
        public string $entityCode,
    ) {}
}
