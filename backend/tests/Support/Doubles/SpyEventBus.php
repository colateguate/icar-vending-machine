<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\Shared\Domain\Bus\Event\DomainEvent;
use App\Shared\Domain\Bus\Event\EventBus;

/**
 * Records what was published instead of publishing it.
 *
 * A spy rather than a mock: the tests ask it afterwards what it received,
 * instead of declaring up front which calls to expect. That keeps them tied to
 * the outcome — "a sale was announced" — rather than to the number of times a
 * method was reached.
 */
final class SpyEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    private array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }

    /**
     * @return list<DomainEvent>
     */
    public function published(): array
    {
        return $this->published;
    }

    /**
     * @param class-string<DomainEvent> $eventClass
     */
    public function hasPublished(string $eventClass): bool
    {
        foreach ($this->published as $event) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }
}
