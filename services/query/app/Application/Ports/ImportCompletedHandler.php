<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\ImportCompletedEvent;

/**
 * Port for handling ImportCompleted events.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface ImportCompletedHandler
{
    /**
     * Handle an ImportCompleted event.
     */
    public function handle(ImportCompletedEvent $event): void;
}
