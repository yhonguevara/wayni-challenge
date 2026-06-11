<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\FileStorage;
use App\Application\Ports\ImportLogRepository;
use App\Infrastructure\Console\LocalstackSetupCommand;
use App\Infrastructure\Console\ProcessBcraFileCommand;
use App\Infrastructure\Notification\NotificationFactory;
use App\Infrastructure\Persistence\Aggregator;
use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Infrastructure\Persistence\StagingLoader;
use App\Infrastructure\Storage\S3FileStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerNotificationSender();
        $this->registerImportLogRepository();
        $this->registerFileStorage();
        $this->registerStagingLoader();
        $this->registerAggregator();
        $this->registerImportOrchestrator();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LocalstackSetupCommand::class,
                ProcessBcraFileCommand::class,
            ]);
        }
    }

    private function registerNotificationSender(): void
    {
        $this->app->bind(NotificationSender::class, function () {
            $driver = (string) env('NOTIFICATION_DRIVER', 'log');

            return NotificationFactory::fromDriver($driver);
        });
    }

    private function registerImportOrchestrator(): void
    {
        $this->app->bind(ImportOrchestrator::class, function () {
            return new ImportOrchestrator(
                stagingLoader: $this->app->make(StagingLoader::class),
                aggregator: $this->app->make(Aggregator::class),
                importLogRepository: $this->app->make(ImportLogRepository::class),
                notificationSender: $this->app->make(NotificationSender::class),
            );
        });
    }

    private function registerStagingLoader(): void
    {
        $this->app->bind(StagingLoader::class, function () {
            return new StagingLoader(
                pdo: \DB::connection()->getPdo(),
            );
        });
    }

    private function registerAggregator(): void
    {
        $this->app->bind(Aggregator::class, function () {
            return new Aggregator(
                pdo: \DB::connection()->getPdo(),
            );
        });
    }

    private function registerImportLogRepository(): void
    {
        $this->app->bind(ImportLogRepository::class, function () {
            return new EloquentImportLogRepository(
                model: new \App\Models\ImportLog(),
            );
        });
    }

    private function registerFileStorage(): void
    {
        $this->app->bind(FileStorage::class, function () {
            return new S3FileStorage();
        });
    }
}
