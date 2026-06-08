<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Events\ImportCompleted;

/**
 * Port for sending import completion notifications.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface NotificationSender
{
    /**
     * Send a notification about a completed import.
     */
    public function send(ImportCompleted $event): void;
}
