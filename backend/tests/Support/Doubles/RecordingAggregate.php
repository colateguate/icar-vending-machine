<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Bus\Event\DomainEvent;

/**
 * Exposes the protected recorder so AggregateRoot can be exercised on its own,
 * without dragging a real aggregate and its business rules into a test that is
 * only about recording and releasing.
 */
final class RecordingAggregate extends AggregateRoot
{
    public function record(DomainEvent $event): void
    {
        $this->recordThat($event);
    }
}
