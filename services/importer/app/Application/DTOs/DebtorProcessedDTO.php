<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * DTO for a DebtorProcessed event received from SQS (consume side).
 *
 * Wire contract (camelCase) mirrors the publish-side domain event toArray().
 * Fields: importId, identificationNumber, maxSituation, totalLoans.
 */
final readonly class DebtorProcessedDTO
{
    public function __construct(
        public string $importId,
        public string $identificationNumber,
        public string $maxSituation,
        public float $totalLoans,
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
            identificationNumber: $data['identificationNumber'],
            maxSituation: $data['maxSituation'],
            totalLoans: (float) $data['totalLoans'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'importId'             => $this->importId,
            'identificationNumber' => $this->identificationNumber,
            'maxSituation'         => $this->maxSituation,
            'totalLoans'           => $this->totalLoans,
        ];
    }
}
