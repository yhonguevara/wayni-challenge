<?php

declare(strict_types=1);

namespace App\Domain\Events;

interface DomainEvent
{
    /**
     * Serialize event to array for JSON encoding.
     */
    public function toArray(): array;

    /**
     * Get the timestamp when the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;
}
