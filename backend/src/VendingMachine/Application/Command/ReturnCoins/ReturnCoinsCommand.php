<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ReturnCoins;

use App\Shared\Domain\Bus\Command\Command;
use App\VendingMachine\Domain\Money\CoinCollection;

/**
 * @implements Command<CoinCollection>
 */
final readonly class ReturnCoinsCommand implements Command
{
}
