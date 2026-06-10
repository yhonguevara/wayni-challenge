<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\EntityProcessedEvent;
use App\Application\Ports\EntityEventHandler;
use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class UpsertEntityHandler implements ShouldQueue, EntityEventHandler
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
    }
}
