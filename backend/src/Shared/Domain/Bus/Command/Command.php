<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

/**
 * An intention to change something, named after what the user is asking for.
 *
 * Commands carry primitives, never value objects. They have to be
 * serialisable and transport-agnostic so the bus can go asynchronous later
 * without the message changing shape, and the handler is where a primitive
 * becomes a domain type — a translation that doubles as validation, since the
 * value object's constructor is what rejects what cannot exist.
 *
 * The type parameter records what dispatching one gives back. Most commands
 * answer with null: a command that returns state would be a query wearing a
 * disguise. The exceptions are the ones whose result physically left the
 * machine and no later question could recover it.
 *
 * @template-covariant TResult
 */
interface Command
{
}
