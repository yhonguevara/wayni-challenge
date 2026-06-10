<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Application\Notification\NotificationSender;
use Aws\Sqs\SqsClient;
use InvalidArgumentException;

/**
 * Factory for creating NotificationSender implementations.
 *
 * Resolves the implementation based on NOTIFICATION_DRIVER environment variable.
 * Supported drivers: log (default), webhook, sqs.
 */
final class NotificationFactory
{
    /**
     * Create a NotificationSender based on the configured driver.
     *
     * LogNotification is ALWAYS included for observability.
     * If a different driver is configured, it's added alongside log.
     *
     * @throws InvalidArgumentException for unknown driver
     */
    public static function fromDriver(string $driver): NotificationSender
    {
        $logNotification = new LogNotification();

        if ($driver === 'log') {
            return $logNotification;
        }

        $additionalSender = match ($driver) {
            'webhook' => new WebhookNotification(
                webhookUrl: (string) env('NOTIFICATION_WEBHOOK_URL', ''),
            ),
            'sqs' => self::createSqsNotification(),
            default => throw new InvalidArgumentException(
                sprintf('Unknown notification driver: %s. Supported: log, webhook, sqs', $driver)
            ),
        };

        return new CompositeNotificationSender([
            $logNotification,
            $additionalSender,
        ]);
    }

    private static function createSqsNotification(): SqsNotification
    {
        $endpoint = (string) env('AWS_ENDPOINT', 'http://localstack:4566');
        $region = (string) env('AWS_DEFAULT_REGION', 'us-east-1');
        $queueUrl = (string) env('NOTIFICATION_QUEUE_URL', '');

        $client = new SqsClient([
            'endpoint' => $endpoint,
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => (string) env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => (string) env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        return new SqsNotification($client, $queueUrl);
    }
}
