<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use Aws\Sqs\SqsClient;

/**
 * SQS-based notification sender.
 *
 * Publishes import completion events to an SQS queue.
 * Used for downstream consumers that need real-time notification.
 */
final class SqsNotification implements NotificationSender
{
    public function __construct(
        private readonly SqsClient $client,
        private readonly string $queueUrl,
    ) {}

    public function send(ImportCompleted $event): void
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode($event->toSnakeCase(), JSON_THROW_ON_ERROR),
            'MessageAttributes' => [
                'event_type' => [
                    'DataType' => 'String',
                    'StringValue' => 'ImportCompleted',
                ],
            ],
        ]);
    }
}
