<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class ImportCompleted implements DomainEvent
{
    public function __construct(
        public string $filename,
        public int $totalDebtors,
        public int $totalEntities,
        public int $durationMs,
        public \DateTimeImmutable $completedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'totalDebtors' => $this->totalDebtors,
            'totalEntities' => $this->totalEntities,
            'durationMs' => $this->durationMs,
            'completedAt' => $this->completedAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
