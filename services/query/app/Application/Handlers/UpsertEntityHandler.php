<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\DTOs\EntityProcessedEvent;
use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpsertEntityHandler implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->queue = 'entity-events';
    }

    public function handle(EntityProcessedEvent $event): void
    {
        Entity::updateOrCreate(
            ['entity_code' => $event->entityCode],
            [
                'total_loan_amount' => $event->totalLoans,
            ],
        );

        Log::info('Entity upserted', [
            'entity_code' => $event->entityCode,
            'total_loans' => $event->totalLoans,
            'import_id' => $event->importId,
        ]);
    }
}
