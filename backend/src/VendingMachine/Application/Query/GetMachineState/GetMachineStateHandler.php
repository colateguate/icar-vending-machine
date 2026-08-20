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
            // Read off the enum rather than asked of the aggregate, because the
            // aggregate does not know it: which coins the slot takes is a fact
            // about this machine model, not about this machine's contents. A
            // pass-through method on the aggregate would only make it look like
            // state that could differ between instances.
            CoinDenomination::cases(),
            $machine->requiresExactChange(),
        );
    }
}
