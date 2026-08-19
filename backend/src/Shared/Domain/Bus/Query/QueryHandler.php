<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * Marks the class that answers a given query. Tagged onto the query bus by the
 * container, for the same reason command handlers are: so the application
 * layer never mentions the framework.
 */
interface QueryHandler
{
}
