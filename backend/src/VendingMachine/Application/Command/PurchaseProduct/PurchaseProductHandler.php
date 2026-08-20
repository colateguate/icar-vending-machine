<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\PurchaseProduct;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\DispensedGoods;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;

/**
 * The canonical handler of this project: it orchestrates and decides nothing.
 *
 * Whether the sale can happen, which coins come back, what the machine looks
 * like afterwards — all of that belongs to the aggregate. This class loads it,
 * hands it the policy it needs, persists the outcome and announces it. If a
 * business rule ever appears in here, it is in the wrong place.
 *
 * The change policy is injected and passed through rather than chosen here,
 * so swapping the algorithm is a container binding, not a code change.
 */
final readonly class PurchaseProductHandler implements CommandHandler
{
    public function __construct(
        private MachineLocator $locator,
        private VendingMachineRepository $repository,
        private EventBus $eventBus,
        private ChangeStrategy $changeStrategy,
    ) {
    }

    public function __invoke(PurchaseProductCommand $command): DispensedGoods
    {
        $machine = $this->locator->locate();

        $dispensed = $machine->purchase(
            ProductSelector::fromString($command->selector),
            $this->changeStrategy,
        );

        $this->repository->save($machine);
        $this->eventBus->publish(...$machine->releaseEvents());

        return $dispensed;
    }
}
