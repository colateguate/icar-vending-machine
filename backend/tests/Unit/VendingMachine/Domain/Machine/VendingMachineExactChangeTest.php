<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

/**
 * The EXACT CHANGE ONLY lamp. A machine that cannot hand back a single coin
 * should say so before the customer feeds it a 1.00 piece, rather than taking
 * the money and refusing the sale.
 *
 * The signal is deliberately narrow — "I can give no change at all" — and not a
 * prediction of whether some future overpayment could be covered. That richer
 * question needs a change policy and a definition of the worst case the brief
 * never gives; the 409 on the purchase itself remains the authoritative answer.
 */
final class VendingMachineExactChangeTest extends TestCase
{
    public function test_a_machine_with_no_dispensable_coins_needs_exact_change(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();

        self::assertTrue($machine->requiresExactChange());
    }

    public function test_a_stocked_reserve_does_not_need_exact_change(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        self::assertFalse($machine->requiresExactChange());
    }

    public function test_a_single_nickel_is_enough_to_turn_the_lamp_off(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([5 => 1])
            ->build();

        self::assertFalse($machine->requiresExactChange());
    }

    /**
     * A till full of 1.00 coins is a till that cannot give change: the brief
     * accepts that coin but never returns it.
     */
    public function test_a_reserve_of_only_one_unit_coins_still_needs_exact_change(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([100 => 20])
            ->build();

        self::assertTrue($machine->requiresExactChange());
    }

    public function test_selling_out_of_change_lights_the_lamp(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([25 => 1, 10 => 1])
            ->build();
        self::assertFalse($machine->requiresExactChange());
        $machine->insert(CoinDenomination::ONE_UNIT);

        $machine->purchase(ProductSelector::fromString('WATER'), new OptimalChangeStrategy());

        self::assertTrue(
            $machine->requiresExactChange(),
            'the last two dispensable coins went out as change for 0.35',
        );
    }

    public function test_a_service_visit_can_turn_the_lamp_off_again(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();
        self::assertTrue($machine->requiresExactChange());

        $machine->service($machine->inventory(), CoinCollection::fromCounts([5 => 20, 10 => 20]), $machine->acceptedCoins());

        self::assertFalse($machine->requiresExactChange());
    }

    public function test_money_in_the_escrow_does_not_turn_the_lamp_off(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        self::assertTrue(
            $machine->requiresExactChange(),
            'coins in escrow still belong to the customer; the till is what pays change',
        );
    }
}
