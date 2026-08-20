<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\InsertCoin;

use App\Shared\Domain\Bus\Command\Command;

/**
 * @implements Command<null>
 */
final readonly class InsertCoinCommand implements Command
{
    public function __construct(public int $coinInCents)
    {
    }
}
