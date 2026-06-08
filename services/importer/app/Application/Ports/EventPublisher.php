<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Events\DebtorProcessed;
use App\Domain\Events\DomainEvent;
use App\Domain\Events\EntityProcessed;
use App\Domain\Events\ImportCompleted;

/**
 * Port for publishing domain events.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface EventPublisher
{
    /**
     * Publish a DebtorProcessed event.
     */
    public function publishDebtorProcessed(DebtorProcessed $event): void;

    /**
     * Publish an EntityProcessed event.
     */
    public function publishEntityProcessed(EntityProcessed $event): void;

    /**
     * Publish an ImportCompleted event.
     */
    public function publishImportCompleted(ImportCompleted $event): void;

    /**
     * Publish a batch of domain events.
     *
     * @param array<int, DomainEvent> $events
     */
    public function publishBatch(array $events): void;
}
