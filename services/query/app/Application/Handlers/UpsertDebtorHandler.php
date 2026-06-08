<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\DTOs\DebtorProcessedEvent;
use App\Models\Debtor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpsertDebtorHandler implements ShouldQueue
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

        Log::info('Debtor upserted', [
            'identification_number' => $event->identificationNumber,
            'max_situation' => $event->maxSituation,
            'total_loans' => $event->totalLoans,
            'import_id' => $event->importId,
        ]);
    }
}
