<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class EntityProcessedEvent
{
    public function __construct(
        public string $entityCode,
        public float $totalLoans,
        public string $importId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entityCode: $data['entityCode'],
            totalLoans: (float) $data['totalLoans'],
            importId: $data['importId'],
        );
    }

    public function toArray(): array
    {
        return [
            'entityCode' => $this->entityCode,
            'totalLoans' => $this->totalLoans,
            'importId' => $this->importId,
        ];
    }
}
