<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\Shared\Domain\Bus\Event\DomainEvent;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;

/**
 * A sale completed: an item left the shelf and change left the till. Carries
 * both sides of the movement, because that is what cash reconciliation needs
 * to answer "does the money in this machine match what it sold".
 */
final readonly class ProductDispensed implements DomainEvent
{
    public function __construct(
        private MachineId $machineId,
        private ProductSelector $selector,
        private Money $price,
        private CoinCollection $change,
    ) {
    }

    public function machineId(): MachineId
    {
        return $this->machineId;
    }

    public function selector(): ProductSelector
    {
        return $this->selector;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function change(): CoinCollection
    {
        return $this->change;
    }
}
