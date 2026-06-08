<?php

declare(strict_types=1);

namespace App\Infrastructure\Handlers;

use App\Application\DTOs\ImportCompletedEvent;
use App\Application\Ports\ImportCompletedHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class LogImportCompletionHandler implements ShouldQueue, ImportCompletedHandler
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct()
    {
        $this->queue = 'import-completed';
    }

    public function handle(ImportCompletedEvent $event): void
    {
        Log::info('Import completed', $event->toArray());
    }
}
