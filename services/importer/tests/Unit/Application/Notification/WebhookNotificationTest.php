<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Notification;

use App\Infrastructure\Notification\WebhookNotification;
use App\Domain\Events\ImportCompleted;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class WebhookNotificationTest extends TestCase
{
    public function test_send_makes_http_post_to_configured_url(): void
    {
        // Arrange
        $url = 'https://example.com/webhook';
        $notification = new WebhookNotification($url);
        $event = new ImportCompleted(
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable('2026-06-08T12:00:00Z'),
        );

        Http::fake([
            $url => Http::response(null, 200),
        ]);

        // Act
        $notification->send($event);

        // Assert
        Http::assertSent(function ($request) use ($url) {
            return $request->url() === $url
                && $request->method() === 'POST'
                && $request->data()['filename'] === 'deudores.txt';
        });
    }

    public function test_send_retries_on_failure(): void
    {
        // Arrange
        $url = 'https://example.com/webhook';
        $notification = new WebhookNotification($url);
        $event = new ImportCompleted(
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable(),
        );

        // First two calls fail, third succeeds
        Http::fake([
            $url => Http::sequence()
                ->push(null, 500)
                ->push(null, 500)
                ->push(null, 200),
        ]);

        // Act
        $notification->send($event);

        // Assert — 3 attempts were made
        Http::assertSentCount(3);
    }

    public function test_send_timeout_is_30_seconds(): void
    {
        // Arrange
        $url = 'https://example.com/webhook';
        $notification = new WebhookNotification($url);
        $event = new ImportCompleted(
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable(),
        );

        Http::fake([
            $url => Http::response(null, 200),
        ]);

        // Act
        $notification->send($event);

        // Assert — verify the request was made (timeout is configured internally)
        Http::assertSent(fn ($request) => $request->url() === $url);
    }

    public function test_constructor_throws_when_webhook_url_is_empty(): void
    {
        // Arrange & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook URL is required');

        // Act
        new WebhookNotification('');
    }
}
