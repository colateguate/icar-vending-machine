<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use App\VendingMachine\Application\Shared\Catalogue;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinCollection;

/**
 * Turns the request's plain arrays into the machine's own vocabulary.
 *
 * Every conversion here is also a check: a price that is not a decimal amount,
 * a selector in the wrong shape, a coin the machine does not take — each is
 * rejected by the value object that would have had to hold it. That is why
 * there is no validation layer in front of this handler.
 */
final readonly class ServiceMachineHandler implements CommandHandler
{
    public function __construct(
        private MachineLocator $locator,
        private VendingMachineRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(ServiceMachineCommand $command): void
    {
        $machine = $this->locator->locate();

        $machine->service(
            Catalogue::fromRows($command->products),
            CoinCollection::fromCounts($command->changeReserve),
        );

        $this->repository->save($machine);
        $this->eventBus->publish(...$machine->releaseEvents());
    }
}
