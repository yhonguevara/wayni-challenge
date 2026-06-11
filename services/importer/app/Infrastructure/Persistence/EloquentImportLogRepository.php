<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\ImportLogRepository;
use App\Models\ImportLog;

/**
 * Eloquent implementation of ImportLogRepository.
 *
 * Wraps the ImportLog model for persistence operations.
 */
final class EloquentImportLogRepository implements ImportLogRepository
{
    public function __construct(
        private readonly ImportLog $model,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ImportLog
    {
        return $this->model->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(string $importId, string $status, array $data = []): void
    {
        $this->model->where('id', $importId)->update(
            array_merge(['status' => $status], $data)
        );
    }

    public function find(string $importId): ?ImportLog
    {
        return $this->model->find($importId);
    }

    public function hasActiveImport(): ?string
    {
        return $this->model
            ->whereIn('status', ['pending', 'processing'])
            ->value('id');
    }
}
