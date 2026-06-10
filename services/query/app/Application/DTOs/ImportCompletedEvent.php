<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class ImportCompletedEvent
{
    public function __construct(
        public string $importId,
        public string $filename,
        public int $totalRecords,
        public int $validRecords,
        public int $invalidRecords,
        public int $durationMs,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            importId: $data['importId'],
            filename: $data['filename'],
            totalRecords: (int) $data['totalRecords'],
            validRecords: (int) $data['validRecords'],
            invalidRecords: (int) $data['invalidRecords'],
            durationMs: (int) $data['durationMs'],
        );
    }

    public function toArray(): array
    {
        return [
            'importId' => $this->importId,
            'filename' => $this->filename,
            'totalRecords' => $this->totalRecords,
            'validRecords' => $this->validRecords,
            'invalidRecords' => $this->invalidRecords,
            'durationMs' => $this->durationMs,
        ];
    }

    /**
     * Snake_case representation for structured logging.
     *
     * Distinct from toArray(), which mirrors the camelCase SQS wire format.
     * Logs use snake_case across both services.
     */
    public function toLogContext(): array
    {
        return [
            'import_id' => $this->importId,
            'filename' => $this->filename,
            'total_records' => $this->totalRecords,
            'valid_records' => $this->validRecords,
            'invalid_records' => $this->invalidRecords,
            'duration_ms' => $this->durationMs,
        ];
    }
}
