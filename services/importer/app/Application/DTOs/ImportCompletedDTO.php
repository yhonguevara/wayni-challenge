<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * DTO for an ImportCompleted event received from SQS (consume side).
 *
 * Wire contract (camelCase) mirrors the publish-side ImportCompleted domain event toArray().
 * Fields: importId, totalDebtors, totalEntities.
 *
 * Note: this is the CONSUME side DTO used by the worker.
 * It is distinct from the PUBLISH side domain event App\Domain\Events\ImportCompleted.
 */
final readonly class ImportCompletedDTO
{
    public function __construct(
        public string $importId,
        public int $totalDebtors,
        public int $totalEntities,
    ) {}

    /**
     * Deserialize from the camelCase SQS message payload.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            importId: $data['importId'],
            totalDebtors: (int) $data['totalDebtors'],
            totalEntities: (int) $data['totalEntities'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'importId'      => $this->importId,
            'totalDebtors'  => $this->totalDebtors,
            'totalEntities' => $this->totalEntities,
        ];
    }
}
