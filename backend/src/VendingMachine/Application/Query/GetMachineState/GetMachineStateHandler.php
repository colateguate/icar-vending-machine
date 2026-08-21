<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineState;

use App\Shared\Domain\Bus\Query\QueryHandler;
use App\VendingMachine\Application\Shared\MachineLocator;

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
            $machine->requiresExactChange(),
        );
    }
}
