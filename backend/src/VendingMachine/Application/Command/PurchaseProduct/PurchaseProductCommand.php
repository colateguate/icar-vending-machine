<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\PurchaseProduct;

use App\Shared\Domain\Bus\Command\Command;
use App\VendingMachine\Domain\Dispensing\DispensedGoods;

/**
 * @implements Command<DispensedGoods>
 */
final readonly class PurchaseProductCommand implements Command
{
    public function __construct(public string $selector)
    {
    }
}
