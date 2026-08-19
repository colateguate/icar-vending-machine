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
            $machine->requiresExactChange(),
        );
    }
}
