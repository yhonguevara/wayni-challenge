<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Handlers\LogImportCompletionHandler;
use App\Application\Handlers\UpsertDebtorHandler;
use App\Application\Handlers\UpsertEntityHandler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
