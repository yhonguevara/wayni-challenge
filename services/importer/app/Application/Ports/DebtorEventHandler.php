<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\DebtorProcessedDTO;

/**
 * Port for handling DebtorProcessed events (consume side).
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface DebtorEventHandler
{
    /**
     * Handle a DebtorProcessed event received from SQS.
     */
    public function handle(DebtorProcessedDTO $dto): void;
}
