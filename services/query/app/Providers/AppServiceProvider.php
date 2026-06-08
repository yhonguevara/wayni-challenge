<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportCompletedHandler;
use App\Infrastructure\Handlers\LogImportCompletionHandler;
use App\Infrastructure\Handlers\UpsertDebtorHandler;
use App\Infrastructure\Handlers\UpsertEntityHandler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DebtorEventHandler::class, UpsertDebtorHandler::class);
        $this->app->bind(EntityEventHandler::class, UpsertEntityHandler::class);
        $this->app->bind(ImportCompletedHandler::class, LogImportCompletionHandler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::after(function ($event, $data): void {
            // Optional: track job completion metrics
        });

        // Map SQS queues to handlers
        $this->configureQueueConnections();
    }

    private function configureQueueConnections(): void
    {
        // Handlers are automatically routed by their $queue property
        // This ensures the queue names match the SQS queue names
        config([
            'queue.connections.sqs.queue' => 'debtor-events',
        ]);
    }
}
