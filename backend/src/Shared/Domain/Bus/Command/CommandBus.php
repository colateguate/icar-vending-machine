<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

/**
 * The one way a command reaches its handler.
 *
 * Declared here so that nothing outside the infrastructure layer ever names a
 * messaging library. It is also the single seam where cross-cutting concerns
 * are expressed once instead of in every use case: transactions, and later
 * logging, metrics or idempotency, all live in the middleware behind this
 * interface rather than being repeated by hand in each handler.
 */
interface CommandBus
{
    /**
     * @template TResult
     *
     * @param Command<TResult> $command
     *
     * @return TResult
     */
    public function dispatch(Command $command): mixed;
}
