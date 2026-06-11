<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class DebtorProcessed implements DomainEvent
{
    public function __construct(
        public string $identificationNumber,
        public string $maxSituation,
        public float $totalLoans,
        public string $importId,
        public \DateTimeImmutable $processedAt = new \DateTimeImmutable(),
        public int $lineNumber = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'identificationNumber' => $this->identificationNumber,
            'maxSituation' => $this->maxSituation,
            'totalLoans' => $this->totalLoans,
            'importId' => $this->importId,
            'occurredAt' => $this->processedAt->format('Y-m-d\TH:i:s\Z'),
            'lineNumber' => $this->lineNumber,
        ];
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
