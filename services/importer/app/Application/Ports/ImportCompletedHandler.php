<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\ImportCompletedDTO;

/**
 * Port for handling ImportCompleted events (consume side).
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface ImportCompletedHandler
{
    /**
     * Handle an ImportCompleted event received from SQS.
     */
    public function handle(ImportCompletedDTO $dto): void;
}
