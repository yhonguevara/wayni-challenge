<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\DebtorProcessedEvent;

/**
 * Port for handling DebtorProcessed events.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface DebtorEventHandler
{
    /**
     * Handle a DebtorProcessed event.
     */
    public function handle(DebtorProcessedEvent $event): void;
}
