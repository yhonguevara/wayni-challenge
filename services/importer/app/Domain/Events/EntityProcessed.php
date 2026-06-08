<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class EntityProcessed implements DomainEvent
{
    public function __construct(
        public string $entityCode,
        public float $totalLoans,
        public string $importId,
    ) {}

    public function toArray(): array
    {
        return [
            'entityCode' => $this->entityCode,
            'totalLoans' => $this->totalLoans,
            'importId' => $this->importId,
            'occurredAt' => $this->occurredAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
