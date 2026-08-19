<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Event;

use App\Shared\Domain\Bus\Event\DomainEvent;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinDenomination;

final readonly class CoinInserted implements DomainEvent
{
    public function __construct(
        private MachineId $machineId,
        private CoinDenomination $coin,
    ) {
    }

    public function machineId(): MachineId
    {
        return $this->machineId;
    }

    public function coin(): CoinDenomination
    {
        return $this->coin;
    }
}
