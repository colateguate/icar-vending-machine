<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ReturnCoins;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinCollection;

/**
 * Answers with the coins it gave back. They fell into the tray, so no later
 * question could tell you which ones they were — that is the test for whether
 * a command is allowed to return something.
 */
final readonly class ReturnCoinsHandler implements CommandHandler
{
    public function __construct(
        private MachineLocator $locator,
        private VendingMachineRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(ReturnCoinsCommand $command): CoinCollection
    {
        $machine = $this->locator->locate();

        $refunded = $machine->returnInsertedCoins();

        $this->repository->save($machine);
        $this->eventBus->publish(...$machine->releaseEvents());

        return $refunded;
    }
}
