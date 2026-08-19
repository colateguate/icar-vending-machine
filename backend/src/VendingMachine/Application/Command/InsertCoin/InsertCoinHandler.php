<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\InsertCoin;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinDenomination;

/**
 * Load, act, save, announce — the same four steps every command handler takes.
 *
 * Turning the plain number into a CoinDenomination is the validation: there is
 * no separate check for "is this a coin we take", because a value the machine
 * does not accept cannot become one.
 */
final readonly class InsertCoinHandler implements CommandHandler
{
    public function __construct(
        private MachineLocator $locator,
        private VendingMachineRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(InsertCoinCommand $command): void
    {
        $machine = $this->locator->locate();

        $machine->insert(CoinDenomination::fromCents($command->coinInCents));

        $this->repository->save($machine);
        $this->eventBus->publish(...$machine->releaseEvents());
    }
}
