<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;

/**
 * Composite notification sender that delegates to multiple senders.
 *
 * Allows sending notifications through multiple channels simultaneously.
 * For example: always log + optionally send to webhook or SQS.
 */
final class CompositeNotificationSender implements NotificationSender
{
    /**
     * @param array<NotificationSender> $senders
     */
    public function __construct(
        private readonly array $senders,
    ) {}

    public function send(ImportCompleted $event): void
    {
        foreach ($this->senders as $sender) {
            $sender->send($event);
        }
    }
}
