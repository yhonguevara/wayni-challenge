<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Notification\NotificationFactory;
use App\Application\Notification\NotificationSender;
use App\Application\Orchestrator\ImportOrchestrator;
use App\Application\Ports\EventPublisher;
use App\Infrastructure\Console\LocalstackSetupCommand;
use App\Infrastructure\Console\ProcessBcraFileCommand;
use App\Infrastructure\Messaging\SqsEventPublisher;
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
                eventPublisher: $this->app->make(EventPublisher::class),
                notificationSender: $this->app->make(NotificationSender::class),
            );
        });
    }
}
