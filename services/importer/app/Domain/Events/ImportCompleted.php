<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class ImportCompleted implements DomainEvent
{
    public function __construct(
        public string $importId,
        public int $totalRecords,
        public int $validRecords,
        public int $invalidRecords,
        public int $durationMs,
    ) {}

    public function toArray(): array
    {
        return [
            'importId' => $this->importId,
            'totalRecords' => $this->totalRecords,
            'validRecords' => $this->validRecords,
            'invalidRecords' => $this->invalidRecords,
            'durationMs' => $this->durationMs,
            'occurredAt' => $this->occurredAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
