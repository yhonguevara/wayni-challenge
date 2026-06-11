<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\DebtorEventHandler;
use App\Application\Ports\EntityEventHandler;
use App\Application\Ports\EventPublisher;
use App\Application\Ports\FileStorage;
use App\Application\Ports\ImportCompletedHandler;
use App\Application\Ports\ImportLogRepository;
use App\Infrastructure\Console\ConsumeEventsCommand;
use App\Infrastructure\Console\LocalstackSetupCommand;
use App\Infrastructure\Console\ProcessBcraFileCommand;
use App\Infrastructure\Handlers\CompletionSentinelHandler;
use App\Infrastructure\Handlers\UpsertDebtorHandler;
use App\Infrastructure\Handlers\UpsertEntityHandler;
use App\Infrastructure\Messaging\SqsEventPublisher;
use App\Infrastructure\Notification\NotificationFactory;
use App\Infrastructure\Persistence\Aggregator;
use App\Infrastructure\Persistence\EloquentImportLogRepository;
use App\Infrastructure\Persistence\StagingLoader;
use App\Infrastructure\Storage\S3FileStorage;
use Aws\Sqs\SqsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerEventPublisher();
        $this->registerNotificationSender();
        $this->registerImportLogRepository();
        $this->registerFileStorage();
        $this->registerEventHandlers();
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
                ConsumeEventsCommand::class,
            ]);
        }
    }

    private function registerEventPublisher(): void
    {
        $this->app->bind(EventPublisher::class, function () {
            $endpoint = (string) env('AWS_ENDPOINT', 'http://localstack:4566');
            $region = (string) env('AWS_DEFAULT_REGION', 'us-east-1');
            $prefix = (string) env('SQS_PREFIX', 'http://localstack:4566/000000000000');

            $client = new SqsClient([
                'endpoint' => $endpoint,
                'region' => $region,
                'version' => 'latest',
                'credentials' => [
                    'key' => (string) env('AWS_ACCESS_KEY_ID', 'test'),
                    'secret' => (string) env('AWS_SECRET_ACCESS_KEY', 'test'),
                ],
            ]);

            return new SqsEventPublisher(
                client: $client,
                debtorQueueUrl: $prefix . '/debtor-events',
                entityQueueUrl: $prefix . '/entity-events',
                importCompletedQueueUrl: $prefix . '/import-completed',
            );
        });
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

    private function registerEventHandlers(): void
    {
        $this->app->bind(DebtorEventHandler::class, function () {
            return new UpsertDebtorHandler(
                repository: $this->app->make(ImportLogRepository::class),
                notificationSender: $this->app->make(NotificationSender::class),
            );
        });

        $this->app->bind(EntityEventHandler::class, function () {
            return new UpsertEntityHandler(
                repository: $this->app->make(ImportLogRepository::class),
                notificationSender: $this->app->make(NotificationSender::class),
            );
        });

        $this->app->bind(ImportCompletedHandler::class, function () {
            return new CompletionSentinelHandler(
                repository: $this->app->make(ImportLogRepository::class),
                notificationSender: $this->app->make(NotificationSender::class),
            );
        });
    }
}
