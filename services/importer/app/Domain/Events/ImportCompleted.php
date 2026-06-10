<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class ImportCompleted implements DomainEvent
{
    public function __construct(
        public string $importId,
        public string $filename,
        public int $totalDebtors,
        public int $totalEntities,
        public int $durationMs,
        public \DateTimeImmutable $completedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'importId' => $this->importId,
            'filename' => $this->filename,
            'totalDebtors' => $this->totalDebtors,
            'totalEntities' => $this->totalEntities,
            'durationMs' => $this->durationMs,
            'completedAt' => $this->completedAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Snake_case representation for structured logging.
     *
     * Distinct from toArray(), which is the camelCase SQS wire format consumed
     * by the query service. Logs use snake_case across both services.
     */
    public function toLogContext(): array
    {
        return [
            'import_id' => $this->importId,
            'filename' => $this->filename,
            'total_debtors' => $this->totalDebtors,
            'total_entities' => $this->totalEntities,
            'duration_ms' => $this->durationMs,
            'completed_at' => $this->completedAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
