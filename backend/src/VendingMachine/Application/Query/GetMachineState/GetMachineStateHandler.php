<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineState;

use App\Shared\Domain\Bus\Query\QueryHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Money\CoinDenomination;

final readonly class GetMachineStateHandler implements QueryHandler
{
    public function __construct(private MachineLocator $locator)
    {
    }

    public function __invoke(GetMachineStateQuery $query): MachineStateView
    {
        $machine = $this->locator->locate();

        return new MachineStateView(
            $machine->inventory()->all(),
            $machine->changeReserve(),
            $machine->insertedCoins(),
            $machine->insertedAmount(),
            // Which coins this machine takes — its own state, and narrower than
            // what the acceptor can read whenever a technician has switched a
            // denomination off. It used to be read off the enum, back when
            // every machine took everything; asking the aggregate is what makes
            // the answer true per machine.
            $machine->acceptedCoins()->all(),
            // And what the acceptor could read if it were told to. This one is
            // read off the enum, because it is a fact about the hardware rather
            // than about this machine — the only place in the read model where
            // that distinction is visible, and the reason the enum survived
            // becoming configuration.
            CoinDenomination::cases(),
            $machine->requiresExactChange(),
            $machine->isOutOfService(),
        );
    }
}
