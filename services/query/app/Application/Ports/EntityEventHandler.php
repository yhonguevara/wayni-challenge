<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\EntityProcessedEvent;

/**
 * Port for handling EntityProcessed events.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface EntityEventHandler
{
    /**
     * Handle an EntityProcessed event.
     */
    public function handle(EntityProcessedEvent $event): void;
}
