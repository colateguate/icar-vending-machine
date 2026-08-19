<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Bus\Event\DomainEvent;

/**
 * Base for aggregate roots: records what happened, and hands it over once.
 *
 * The aggregate never publishes anything itself — publishing needs a bus, and
 * a bus is infrastructure. It records; the application layer drains the events
 * after the write succeeds and publishes them. That ordering is what keeps the
 * domain from announcing something that a failed transaction then rolls back.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
