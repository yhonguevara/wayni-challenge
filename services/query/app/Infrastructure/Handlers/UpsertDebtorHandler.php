<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\DebtorProcessedEvent;
use App\Application\Ports\DebtorEventHandler;
use App\Models\Debtor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class UpsertDebtorHandler implements ShouldQueue, DebtorEventHandler
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->queue = 'debtor-events';
    }

    public function handle(DebtorProcessedEvent $event): void
    {
        Debtor::updateOrCreate(
            ['identification_number' => $event->identificationNumber],
            [
                'max_situation' => $event->maxSituation,
                'total_loan_amount' => $event->totalLoans,
            ],
        );
    }
}
