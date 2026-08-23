<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\Shared\Domain\Bus\Event\DomainEvent;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinCollection;

/**
 * A technician set what the machine stocks, how much change it can pay and
 * which coins it takes. Carries the loaded state so an audit trail can answer
 * "what was in there after the visit" without replaying everything since —
 * including which denominations were switched on or off, which is the half a
 * count of coins cannot show.
 */
final readonly class MachineServiced implements DomainEvent
{
    public function __construct(
        private MachineId $machineId,
        private Inventory $inventory,
        private CoinCollection $changeReserve,
        private AcceptedCoins $acceptedCoins,
    ) {
    }

    public function machineId(): MachineId
    {
        return $this->machineId;
    }

    public function inventory(): Inventory
    {
        return $this->inventory;
    }

    public function changeReserve(): CoinCollection
    {
        return $this->changeReserve;
    }

    public function acceptedCoins(): AcceptedCoins
    {
        return $this->acceptedCoins;
    }
}
