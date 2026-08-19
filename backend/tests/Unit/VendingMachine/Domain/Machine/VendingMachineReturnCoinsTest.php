<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

final class VendingMachineReturnCoinsTest extends TestCase
{
    /**
     * Example 2 from the brief, as executable specification:
     *
     *   0.10, 0.10, RETURN-COIN  ->  0.10, 0.10
     */
    public function test_example_2_return_coin_gives_back_the_coins_that_were_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TEN_CENTS);
        $machine->insert(CoinDenomination::TEN_CENTS);

        $returned = $machine->returnInsertedCoins();

        self::assertTrue(
            $returned->equals(CoinCollection::of(CoinDenomination::TEN_CENTS, CoinDenomination::TEN_CENTS)),
            'the machine must hand back exactly 0.10 and 0.10',
        );
        self::assertTrue($machine->insertedCoins()->isEmpty());
        self::assertTrue($machine->insertedAmount()->isZero());
    }

    public function test_it_returns_the_very_coins_inserted_not_an_equivalent_amount(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::FIVE_CENTS);

        $returned = $machine->returnInsertedCoins();

        self::assertTrue($returned->equals(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::FIVE_CENTS,
        )), 'a refund of 0.30 as three dimes would be the wrong coins');
    }

    public function test_returning_with_nothing_inserted_gives_back_nothing(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        self::assertTrue($machine->returnInsertedCoins()->isEmpty());
    }

    public function test_returning_does_not_touch_the_change_reserve(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([25 => 4])
            ->build();
        $machine->insert(CoinDenomination::TEN_CENTS);

        $machine->returnInsertedCoins();

        self::assertSame(4, $machine->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(0, $machine->changeReserve()->countOf(CoinDenomination::TEN_CENTS));
    }

    public function test_a_refund_is_recorded_with_the_coins_that_left(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TEN_CENTS);
        $machine->releaseEvents();

        $machine->returnInsertedCoins();

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CoinsRefunded::class, $events[0]);
        self::assertTrue($events[0]->coins()->equals(CoinCollection::of(CoinDenomination::TEN_CENTS)));
        self::assertTrue(
            $events[0]->machineId()->equals($machine->id()),
            'cash reconciliation cannot use a movement it cannot attribute to a machine',
        );
    }

    public function test_returning_nothing_records_nothing(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->returnInsertedCoins();

        self::assertSame(
            [],
            $machine->releaseEvents(),
            'no coins left the machine, so there is nothing to report',
        );
    }
}
