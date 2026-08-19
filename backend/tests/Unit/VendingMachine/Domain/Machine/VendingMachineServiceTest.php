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
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class VendingMachineServiceTest extends TestCase
{
    public function test_servicing_replaces_the_inventory_outright(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty());

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

        $machine->service(self::onlyWater(3), CoinCollection::fromCounts([5 => 2]));

        self::assertSame(2, $machine->changeReserve()->countOf(CoinDenomination::FIVE_CENTS));
        self::assertSame(0, $machine->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(0, $machine->changeReserve()->countOf(CoinDenomination::TEN_CENTS));
    }

    public function test_servicing_returns_money_a_customer_had_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::TEN_CENTS)
            ->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty());

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

        $machine->service(self::onlyWater(3), CoinCollection::fromCounts([5 => 1]));

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

        $machine->service(self::onlyWater(3), CoinCollection::empty());

        $events = $machine->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(CoinsRefunded::class, $events[0]);
        self::assertInstanceOf(MachineServiced::class, $events[1]);
    }

    public function test_servicing_an_empty_machine_records_only_the_service(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(self::onlyWater(3), CoinCollection::empty());

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MachineServiced::class, $events[0]);
    }

    public function test_the_service_event_carries_what_was_loaded(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $loaded = self::onlyWater(3);
        $reserve = CoinCollection::fromCounts([25 => 4]);

        $machine->service($loaded, $reserve);

        $events = $machine->releaseEvents();
        self::assertInstanceOf(MachineServiced::class, $events[0]);
        self::assertTrue($events[0]->inventory()->equals($loaded));
        self::assertTrue($events[0]->changeReserve()->equals($reserve));
        self::assertTrue($events[0]->machineId()->equals($machine->id()));
    }

    public function test_servicing_can_empty_the_machine(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->service(Inventory::empty(), CoinCollection::empty());

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
