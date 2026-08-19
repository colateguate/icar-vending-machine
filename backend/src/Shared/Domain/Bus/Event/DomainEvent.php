<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

/**
 * Something that happened in the domain, stated in the past tense.
 *
 * A marker with no members. In particular there is no occurredOn(): a
 * timestamp would have to come from a clock, and injecting a clock port into
 * the domain to satisfy an audit line nobody has asked for yet is a cost with
 * no buyer. The logger that consumes these events already stamps them.
 *
 * Domain events carry value objects rather than primitives, unlike commands.
 * The asymmetry is deliberate: a command arrives from outside and is built
 * from a JSON payload, whereas an event is born inside the domain and consumed
 * inside it. Publishing outside this process would call for an integration
 * event with a primitive payload — a different type, on purpose.
 */
interface DomainEvent
{
}
