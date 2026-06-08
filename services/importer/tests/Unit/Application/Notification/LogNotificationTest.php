<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Notification;

use App\Application\Notification\LogNotification;
use App\Domain\Events\ImportCompleted;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogNotificationTest extends TestCase
{
    public function test_send_logs_structured_json_with_correct_fields(): void
    {
        // Arrange
        $notification = new LogNotification();
        $event = new ImportCompleted(
            filename: 'deudores.txt',
            totalDebtors: 150,
            totalEntities: 5,
            durationMs: 2500,
            completedAt: new \DateTimeImmutable('2026-06-08T12:00:00Z'),
        );

        Log::shouldReceive('info')
            ->once()
            ->with('Import completed', [
                'filename' => 'deudores.txt',
                'totalDebtors' => 150,
                'totalEntities' => 5,
                'durationMs' => 2500,
                'completedAt' => '2026-06-08T12:00:00Z',
            ]);

        // Act
        $notification->send($event);

        // Assert — Log::info expectations verified by Mockery
    }

    public function test_send_uses_log_facade(): void
    {
        // Arrange
        $notification = new LogNotification();
        $event = new ImportCompleted(
            filename: 'test.txt',
            totalDebtors: 10,
            totalEntities: 2,
            durationMs: 500,
            completedAt: new \DateTimeImmutable(),
        );

        Log::shouldReceive('info')->once();

        // Act
        $notification->send($event);

        // Assert — if Log::info was not called, Mockery would fail
        Log::shouldHaveReceived('info')->once();
    }
}
