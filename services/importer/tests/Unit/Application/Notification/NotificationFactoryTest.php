<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Notification;

use App\Infrastructure\Notification\CompositeNotificationSender;
use App\Infrastructure\Notification\LogNotification;
use App\Infrastructure\Notification\NotificationFactory;
use App\Infrastructure\Notification\SqsNotification;
use App\Infrastructure\Notification\WebhookNotification;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationFactoryTest extends TestCase
{
    public function test_from_driver_log_returns_log_notification(): void
    {
        // Act
        $sender = NotificationFactory::fromDriver('log');

        // Assert
        $this->assertInstanceOf(LogNotification::class, $sender);
    }

    public function test_from_driver_webhook_returns_composite_with_log_and_webhook(): void
    {
        // Arrange
        $this->app['config']->set('notification.webhook_url', 'https://example.com/hook');
        putenv('NOTIFICATION_WEBHOOK_URL=https://example.com/hook');

        // Act
        $sender = NotificationFactory::fromDriver('webhook');

        // Assert
        $this->assertInstanceOf(CompositeNotificationSender::class, $sender);

        // Cleanup
        putenv('NOTIFICATION_WEBHOOK_URL');
    }

    public function test_from_driver_sqs_returns_composite_with_log_and_sqs(): void
    {
        // Act
        $sender = NotificationFactory::fromDriver('sqs');

        // Assert
        $this->assertInstanceOf(CompositeNotificationSender::class, $sender);
    }

    public function test_from_driver_invalid_throws_exception(): void
    {
        // Arrange & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown notification driver: invalid');

        // Act
        NotificationFactory::fromDriver('invalid');
    }
}
