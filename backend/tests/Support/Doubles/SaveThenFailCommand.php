<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\Shared\Domain\Bus\Command\Command;

/**
 * A use case that writes and then fails, which no real one does on purpose.
 *
 * It exists because the transaction middleware can only be proved by a command
 * that gets halfway. Everything else about it is deliberately boring: the
 * question is what the database looks like afterwards, not what this does.
 *
 * @implements Command<null>
 */
final readonly class SaveThenFailCommand implements Command
{
    public function __construct(public string $machineId)
    {
    }
}
