<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class DebtorProcessedEvent
{
    public function __construct(
        public string $identificationNumber,
        public string $maxSituation,
        public float $totalLoans,
        public string $importId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            identificationNumber: $data['identificationNumber'],
            maxSituation: $data['maxSituation'],
            totalLoans: (float) $data['totalLoans'],
            importId: $data['importId'],
        );
    }

    public function toArray(): array
    {
        return [
            'identificationNumber' => $this->identificationNumber,
            'maxSituation' => $this->maxSituation,
            'totalLoans' => $this->totalLoans,
            'importId' => $this->importId,
        ];
    }
}
