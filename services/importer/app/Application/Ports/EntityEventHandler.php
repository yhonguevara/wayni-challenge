<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\EntityProcessedDTO;

/**
 * Port for handling EntityProcessed events (consume side).
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface EntityEventHandler
{
    /**
     * Handle an EntityProcessed event received from SQS.
     */
    public function handle(EntityProcessedDTO $dto): void;
}
