<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\Shared\Domain\Bus\Event\DomainEvent;

/**
 * A stand-in event with no payload. Using a real domain event here would tie
 * a test about the recording mechanism to whatever that event happens to
 * carry today.
 */
final class FakeDomainEvent implements DomainEvent
{
}
