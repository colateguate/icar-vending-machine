<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\Shared\Domain\Bus\Event\DomainEvent;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinCollection;

/**
 * Coins physically left the machine and went back to the customer. Recorded
 * whether the refund was asked for or handed out by a technician opening the
 * machine, because cash reconciliation cares about the movement, not the
 * reason.
 */
final readonly class CoinsRefunded implements DomainEvent
{
    public function __construct(
        private MachineId $machineId,
        private CoinCollection $coins,
    ) {
    }

    public function machineId(): MachineId
    {
        return $this->machineId;
    }

    public function coins(): CoinCollection
    {
        return $this->coins;
    }
}
