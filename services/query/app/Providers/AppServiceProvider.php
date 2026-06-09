<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\ImportCompletedHandler;
use App\Infrastructure\Console\ConsumeEventsCommand;
use App\Infrastructure\Handlers\LogImportCompletionHandler;
use App\Infrastructure\Handlers\UpsertDebtorHandler;
use App\Infrastructure\Handlers\UpsertEntityHandler;
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
        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsumeEventsCommand::class,
            ]);
        }
    }
}
