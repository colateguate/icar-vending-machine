<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ProvisionMachine;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use App\VendingMachine\Application\Shared\Catalogue;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;

/**
 * Creates the machine if it is not there, and does nothing at all if it is.
 *
 * Idempotent because of where it runs: the container entrypoint calls it on
 * every start (ticket 13). A version that reset the catalogue would wipe the
 * last technician's visit every time the service restarted, which is the kind
 * of data loss that only shows up in production.
 *
 * The machine is installed empty and then serviced, rather than being born
 * full. That is what actually happens — a machine is delivered and a person
 * loads it — and it means the rule about what loading a machine does lives in
 * exactly one place, the aggregate's service().
 */
final readonly class ProvisionMachineHandler implements CommandHandler
{
    public function __construct(
        private MachineLocator $locator,
        private VendingMachineRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(ProvisionMachineCommand $command): void
    {
        if ($this->locator->isProvisioned()) {
            return;
        }

        $accepted = AcceptedCoins::of(...array_map(
            CoinDenomination::fromCents(...),
            $command->acceptedCoins,
        ));

        $machine = VendingMachine::provision(
            $this->locator->machineId(),
            Inventory::empty(),
            CoinCollection::empty(),
            $accepted,
        );

        $machine->service(
            Catalogue::fromRows($command->products),
            CoinCollection::fromCounts($command->changeReserve),
            $accepted,
        );

        $this->repository->save($machine);
        $this->eventBus->publish(...$machine->releaseEvents());
    }
}
