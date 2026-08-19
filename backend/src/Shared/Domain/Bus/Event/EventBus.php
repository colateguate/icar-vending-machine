<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

/**
 * Announces what already happened.
 *
 * Publishing is separate from recording on purpose. The aggregate records
 * events while it changes; the handler drains and publishes them only after
 * the write has succeeded. Doing it the other way round would announce a sale
 * that a failed transaction then rolled back.
 *
 * An event with no listeners is normal, not an error — that is the difference
 * between announcing something and asking for it to be done.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
