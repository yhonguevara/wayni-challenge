<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Events\ImportCompleted;
use Illuminate\Support\Facades\Log;

/**
 * Log-based notification sender.
 *
 * Writes structured JSON to the application log.
 * Default driver — safe for all environments.
 */
final class LogNotification implements NotificationSender
{
    public function send(ImportCompleted $event): void
    {
        Log::info('Import completed', $event->toArray());
    }
}
