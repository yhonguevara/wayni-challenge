<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Events\DomainEvent;

/**
 * Port for publishing domain events.
 *
 * Infrastructure layer implements this interface.
 * Application layer depends on this interface, not the implementation.
 */
interface EventPublisher
{
    /**
     * Publish a single domain event.
     */
    public function publish(DomainEvent $event): void;

    /**
     * Publish a batch of domain events.
     *
     * @param array<int, DomainEvent> $events
     */
    public function publishBatch(array $events): void;
}
