<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Notification;

use App\Application\Notification\NotificationSender;
use App\Domain\Events\ImportCompleted;
use App\Infrastructure\Notification\CompositeNotificationSender;
use Tests\TestCase;

class CompositeNotificationSenderTest extends TestCase
{
    public function test_send_delegates_to_all_senders(): void
    {
        // Arrange
        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'test.txt',
            totalDebtors: 95,
            totalEntities: 5,
            durationMs: 10500,
            completedAt: new \DateTimeImmutable('2026-06-10T12:00:00Z')
        );

        $sender1 = $this->createMock(NotificationSender::class);
        $sender1->expects($this->once())
            ->method('send')
            ->with($event);

        $sender2 = $this->createMock(NotificationSender::class);
        $sender2->expects($this->once())
            ->method('send')
            ->with($event);

        $composite = new CompositeNotificationSender([$sender1, $sender2]);

        // Act
        $composite->send($event);
    }

    public function test_send_with_empty_senders_does_nothing(): void
    {
        // Arrange
        $event = new ImportCompleted(
            importId: 'import-123',
            filename: 'test.txt',
            totalDebtors: 95,
            totalEntities: 5,
            durationMs: 10500,
            completedAt: new \DateTimeImmutable('2026-06-10T12:00:00Z')
        );

        $composite = new CompositeNotificationSender([]);

        // Assert — the only contract here is "does not throw"; declaring this
        // explicitly keeps PHPUnit from flagging the test as risky.
        $this->expectNotToPerformAssertions();

        // Act
        $composite->send($event);
    }
}
