<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * Asks a question and returns the answer.
 *
 * Unlike the command bus, a missing handler here is always a bug: a question
 * nobody can answer is not a valid state of the system, so the bus is
 * configured to fail loudly rather than return nothing.
 */
interface QueryBus
{
    /**
     * @template TResponse
     *
     * @param Query<TResponse> $query
     *
     * @return TResponse
     */
    public function ask(Query $query): mixed;
}
