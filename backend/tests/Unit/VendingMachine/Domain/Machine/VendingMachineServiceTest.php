<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class VendingMachineServiceTest extends TestCase
{
    public function test_servicing_replaces_the_inventory_outright(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty(), $machine->acceptedCoins());

        self::assertSame(3, $machine->inventory()->find(self::selector('WATER'))->available()->units());
        self::assertFalse(
            $machine->inventory()->has(self::selector('SODA')),
            'SERVICE sets what the machine stocks, it does not top up what was there',
        );
    }

    public function test_servicing_replaces_the_change_reserve_outright(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([25 => 10, 10 => 10])
            ->build();

        $machine->service(self::onlyWater(3), CoinCollection::fromCounts([5 => 2]), $machine->acceptedCoins());

        self::assertSame(2, $machine->changeReserve()->countOf(CoinDenomination::FIVE_CENTS));
        self::assertSame(0, $machine->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(0, $machine->changeReserve()->countOf(CoinDenomination::TEN_CENTS));
    }

    public function test_servicing_returns_money_a_customer_had_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::TEN_CENTS)
            ->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty(), $machine->acceptedCoins());

        self::assertTrue(
            $machine->insertedCoins()->isEmpty(),
            'a technician opening the machine gives the customer their money back',
        );
        self::assertTrue($machine->insertedAmount()->isZero());
    }

    public function test_refunded_coins_do_not_end_up_in_the_new_change_reserve(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS)
            ->build();

        $machine->service(self::onlyWater(3), CoinCollection::fromCounts([5 => 1]), $machine->acceptedCoins());

        self::assertTrue(
            $machine->changeReserve()->equals(CoinCollection::fromCounts([5 => 1])),
            'the reserve is exactly what the technician loaded, nothing more',
        );
        self::assertTrue($machine->changeReserve()->total()->equals(Money::fromDecimalString('0.05')));
    }

    public function test_servicing_records_the_refund_before_the_service(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withInsertedCoins(CoinDenomination::TEN_CENTS)
            ->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty(), $machine->acceptedCoins());

        $events = $machine->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(CoinsRefunded::class, $events[0]);
        self::assertInstanceOf(MachineServiced::class, $events[1]);
    }

    public function test_servicing_an_empty_machine_records_only_the_service(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty(), $machine->acceptedCoins());

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
    }

    public function test_the_service_event_carries_what_was_loaded(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $loaded = self::onlyWater(3);
        $reserve = CoinCollection::fromCounts([25 => 4]);

        $machine->service($loaded, $reserve, $machine->acceptedCoins());

        $events = $machine->releaseEvents();
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertTrue($events[0]->inventory()->equals($loaded));
        self::assertTrue($events[0]->changeReserve()->equals($reserve));
        self::assertTrue($events[0]->acceptedCoins()->equals($machine->acceptedCoins()));
        self::assertTrue($events[0]->machineId()->equals($machine->id()));
    }

    /**
     * A visit declares the whole machine, the coin acceptor included: which
     * coins it takes is configuration a technician sets, not a property of the
     * model. Absolute values here as everywhere else in SERVICE.
     */
    public function test_servicing_replaces_which_coins_the_machine_takes(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::FIVE_CENTS, CoinDenomination::ONE_UNIT)
            ->build();

        $machine->service(
            self::onlyWater(3),
            CoinCollection::empty(),
            AcceptedCoins::of(CoinDenomination::FIFTY_CENTS, CoinDenomination::TWO_UNITS),
        );

        self::assertTrue($machine->acceptedCoins()->accepts(CoinDenomination::FIFTY_CENTS));
        self::assertFalse(
            $machine->acceptedCoins()->accepts(CoinDenomination::FIVE_CENTS),
            'SERVICE sets which coins the machine takes, it does not add to them',
        );
    }

    /**
     * The till may hold denominations the machine no longer takes, and the
     * technician has to be able to say so: SERVICE states what is in the
     * machine, and refusing to hear it would make the truth unsayable.
     */
    public function test_a_visit_can_declare_coins_the_machine_no_longer_takes(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(
            self::onlyWater(3),
            CoinCollection::fromCounts([50 => 4]),
            AcceptedCoins::of(CoinDenomination::ONE_UNIT),
        );

        self::assertSame(4, $machine->changeReserve()->countOf(CoinDenomination::FIFTY_CENTS));
        self::assertTrue($machine->requiresExactChange(), 'money it may not hand back does not count as change');
    }

    public function test_servicing_can_empty_the_machine(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(Inventory::empty(), CoinCollection::empty(), $machine->acceptedCoins());

        self::assertTrue($machine->inventory()->isEmpty());
        self::assertTrue($machine->changeReserve()->isEmpty());
    }

    private static function onlyWater(int $units): Inventory
    {
        return Inventory::of(new Product(
            self::selector('WATER'),
            'Water',
            Money::fromDecimalString('0.65'),
            Quantity::of($units),
        ));
    }

    private static function selector(string $value): ProductSelector
    {
        return ProductSelector::fromString($value);
    }
}
