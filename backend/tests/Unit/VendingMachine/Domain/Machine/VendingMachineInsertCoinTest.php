<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Event\CoinInserted;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class VendingMachineInsertCoinTest extends TestCase
{
    public function test_a_provisioned_machine_holds_no_inserted_money(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        self::assertTrue($machine->insertedAmount()->isZero());
        self::assertTrue($machine->insertedCoins()->isEmpty());
    }

    public function test_inserting_a_coin_adds_it_to_the_escrow(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        self::assertSame(1, $machine->insertedCoins()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertTrue($machine->insertedAmount()->equals(Money::fromDecimalString('0.25')));
    }

    public function test_inserted_coins_accumulate(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->insert(CoinDenomination::ONE_UNIT);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        self::assertTrue(
            $machine->insertedAmount()->equals(Money::fromDecimalString('1.50')),
            'the coin sequence of example 1 in the brief',
        );
    }

    public function test_inserted_coins_do_not_join_the_change_reserve(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        self::assertTrue(
            $machine->changeReserve()->isEmpty(),
            'money in escrow still belongs to the customer until a sale completes',
        );
    }

    public function test_inserting_a_coin_records_it(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->insert(CoinDenomination::FIVE_CENTS);

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CoinInserted::class, $events[0]);
        self::assertSame(CoinDenomination::FIVE_CENTS, $events[0]->coin());
        self::assertTrue($events[0]->machineId()->equals($machine->id()));
    }

    public function test_each_inserted_coin_is_recorded_separately(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->insert(CoinDenomination::FIVE_CENTS);
        $machine->insert(CoinDenomination::TEN_CENTS);

        self::assertCount(2, $machine->releaseEvents());
    }

    public function test_inserting_leaves_the_inventory_alone(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $before = $machine->inventory();

        $machine->insert(CoinDenomination::ONE_UNIT);

        self::assertTrue($before->equals($machine->inventory()));
    }
}
