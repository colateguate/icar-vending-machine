<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Command;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\SpyEventBus;
use App\VendingMachine\Application\Command\ProvisionMachine\ProvisionMachineCommand;
use App\VendingMachine\Application\Command\ProvisionMachine\ProvisionMachineHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

/**
 * How the first machine comes into existence.
 *
 * Every other use case assumes there is a machine; this is the one that does
 * not. It is separate from SERVICE on purpose: a technician services a machine
 * that exists, and asking that use case to also create one would mean nobody
 * could tell "restock the lobby machine" from "there was no lobby machine, so
 * I made one".
 */
final class ProvisionMachineHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_it_creates_a_machine_that_was_not_there(): void
    {
        $repository = new InMemoryVendingMachineRepository();

        self::handler($repository, new SpyEventBus())(self::aProvisioning());

        $water = $repository
            ->find(MachineId::fromString(self::MACHINE_ID))
            ->inventory()
            ->find(ProductSelector::fromString('WATER'));

        // Every field of the row, not just its presence: the payload passes
        // through Catalogue::fromRows on its way to becoming value objects,
        // and a field dropped or swapped there produces a machine that looks
        // stocked and sells the wrong thing.
        self::assertSame('Water', $water->name());
        self::assertSame('0.65', $water->price()->toDecimalString());
        self::assertSame(10, $water->available()->units());
    }

    public function test_it_loads_the_change_the_technician_brought(): void
    {
        $repository = new InMemoryVendingMachineRepository();

        self::handler($repository, new SpyEventBus())(self::aProvisioning());

        $reserve = $repository->find(MachineId::fromString(self::MACHINE_ID))->changeReserve();
        self::assertSame(4, $reserve->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame('1.00', $reserve->total()->toDecimalString());
    }

    /**
     * The event is what an audit trail reads later, so what it carries is the
     * behaviour — asserting only its class would leave every field inside it
     * free to be wrong.
     */
    public function test_it_announces_what_was_loaded(): void
    {
        $events = new SpyEventBus();

        self::handler(new InMemoryVendingMachineRepository(), $events)(self::aProvisioning());

        $announcement = $events->published()[0];
        self::assertInstanceOf(MachineServiced::class, $announcement);
        self::assertSame(self::MACHINE_ID, $announcement->machineId()->value());
        self::assertSame(
            '0.65',
            $announcement->inventory()->find(ProductSelector::fromString('WATER'))->price()->toDecimalString(),
        );
        self::assertSame(4, $announcement->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    /**
     * Running it twice must be safe, because it runs on every deployment: the
     * container entrypoint calls it before the API is up (ticket 13). A second
     * run that reset the catalogue would wipe a technician's last visit every
     * time the service restarted.
     */
    public function test_it_leaves_an_existing_machine_exactly_as_it_was(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(
            VendingMachineBuilder::aMachine()
                ->withId(self::MACHINE_ID)
                ->withProduct('TEA', 'Iced Tea', '0.80', 2)
                ->withInsertedCoins(CoinDenomination::TEN_CENTS)
                ->build(),
        );

        self::handler($repository, new SpyEventBus())(self::aProvisioning());

        $machine = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertTrue($machine->inventory()->has(ProductSelector::fromString('TEA')));
        self::assertFalse($machine->inventory()->has(ProductSelector::fromString('WATER')));
        self::assertSame('0.10', $machine->insertedAmount()->toDecimalString());
    }

    public function test_it_announces_nothing_when_there_was_already_a_machine(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());
        $events = new SpyEventBus();

        self::handler($repository, $events)(self::aProvisioning());

        self::assertSame([], $events->published(), 'nothing happened, so nothing is announced');
    }

    private static function aProvisioning(): ProvisionMachineCommand
    {
        return new ProvisionMachineCommand(
            [['selector' => 'WATER', 'name' => 'Water', 'price' => '0.65', 'count' => 10]],
            [25 => 4],
        );
    }

    private static function handler(
        InMemoryVendingMachineRepository $repository,
        SpyEventBus $events,
    ): ProvisionMachineHandler {
        return new ProvisionMachineHandler(
            new MachineLocator($repository, self::MACHINE_ID),
            $repository,
            $events,
        );
    }
}
