<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * DTO for an EntityProcessed event received from SQS (consume side).
 *
 * Wire contract (camelCase) mirrors the publish-side domain event toArray().
 * Fields: importId, entityCode, totalLoans.
 */
final readonly class EntityProcessedDTO
{
    public function __construct(
        public string $importId,
        public string $entityCode,
        public float $totalLoans,
        public int $lineNumber = 0,
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
            entityCode: $data['entityCode'],
            totalLoans: (float) $data['totalLoans'],
            lineNumber: isset($data['lineNumber']) ? (int) $data['lineNumber'] : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'importId'   => $this->importId,
            'entityCode' => $this->entityCode,
            'totalLoans' => $this->totalLoans,
        ];
    }
}
