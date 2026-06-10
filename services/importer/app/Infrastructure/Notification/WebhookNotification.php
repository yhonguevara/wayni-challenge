<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Webhook notification sender.
 *
 * Sends an HTTP POST to a configured URL with retry logic.
 * Throws InvalidArgumentException if webhook URL is not configured.
 */
final class WebhookNotification implements NotificationSender
{
    private const TIMEOUT_SECONDS = 30;
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly string $webhookUrl,
    ) {
        if ($webhookUrl === '') {
            throw new InvalidArgumentException(
                'Webhook URL is required. Set NOTIFICATION_WEBHOOK_URL environment variable.'
            );
        }
    }

    public function send(ImportCompleted $event): void
    {
        $payload = $event->toSnakeCase();

        Http::timeout(self::TIMEOUT_SECONDS)
            ->retry(self::MAX_RETRIES, 1000)
            ->post($this->webhookUrl, $payload);
    }
}
