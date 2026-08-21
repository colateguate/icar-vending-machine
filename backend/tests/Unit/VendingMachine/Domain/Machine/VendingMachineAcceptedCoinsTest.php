<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\CoinNotAccepted;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

/**
 * Which coins this machine takes, and everything that follows from turning one
 * off. The rule in one sentence: a machine never hands back a coin it would
 * refuse to take, so money left inside from before is stranded rather than
 * dispensed.
 */
final class VendingMachineAcceptedCoinsTest extends TestCase
{
    public function test_it_refuses_a_coin_it_has_been_told_not_to_take(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::FIVE_CENTS, CoinDenomination::ONE_UNIT)
            ->build();

        $this->expectException(CoinNotAccepted::class);

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
    }

    public function test_a_refused_coin_leaves_the_escrow_untouched(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::ONE_UNIT)
            ->withInsertedCoins(CoinDenomination::ONE_UNIT)
            ->build();

        try {
            $machine->insert(CoinDenomination::FIVE_CENTS);
        } catch (CoinNotAccepted) {
        }

        self::assertTrue($machine->insertedCoins()->equals(CoinCollection::of(CoinDenomination::ONE_UNIT)));
    }

    /**
     * The machine the technician switched off: it reads no coin, so no escrow
     * can ever be built and no sale can ever be paid for. Out of service is a
     * consequence of the model, not a flag beside it.
     */
    public function test_a_machine_that_accepts_nothing_is_out_of_service(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->acceptingNothing()->build();

        self::assertTrue($machine->isOutOfService());

        $this->expectException(CoinNotAccepted::class);

        $machine->insert(CoinDenomination::ONE_UNIT);
    }

    public function test_a_machine_that_takes_one_coin_is_in_service(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::ONE_UNIT)
            ->build();

        self::assertFalse($machine->isOutOfService());
    }

    /**
     * The coins that were already inside when their denomination was switched
     * off. They are the machine's money and they stay the machine's money —
     * they simply never come back out.
     */
    public function test_change_never_includes_a_coin_the_machine_no_longer_takes(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::ONE_UNIT)
            ->withChangeReserve([50 => 4])
            ->withInsertedCoins(CoinDenomination::ONE_UNIT, CoinDenomination::TWENTY_FIVE_CENTS)
            ->build();

        $this->expectException(CannotDispenseChange::class);

        // 1.25 for a 0.65 water owes 0.60, and the only coins that could pay it
        // are the stranded fifties.
        $machine->purchase(ProductSelector::fromString('WATER'), new OptimalChangeStrategy());
    }

    public function test_stranded_coins_stay_in_the_reserve_after_a_sale(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::TEN_CENTS, CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::ONE_UNIT)
            ->withChangeReserve([50 => 4, 25 => 1, 10 => 1])
            ->withInsertedCoins(CoinDenomination::ONE_UNIT)
            ->build();

        $dispensed = $machine->purchase(ProductSelector::fromString('WATER'), new OptimalChangeStrategy());

        self::assertTrue(
            $dispensed->change()->equals(CoinCollection::fromCounts([25 => 1, 10 => 1])),
            'the 0.35 owed comes from the coins the machine still takes',
        );
        self::assertSame(
            4,
            $machine->changeReserve()->countOf(CoinDenomination::FIFTY_CENTS),
            'narrowing the pool must not make the machine forget money it holds',
        );
    }

    /**
     * The lamp answers "can this machine pay change at all", and a till full of
     * coins it may not hand back cannot.
     */
    public function test_a_reserve_of_only_stranded_coins_lights_the_exact_change_lamp(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::ONE_UNIT)
            ->withChangeReserve([50 => 20])
            ->build();

        self::assertTrue($machine->requiresExactChange());
    }

    public function test_a_reserve_the_machine_can_pay_from_leaves_the_lamp_off(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->accepting(CoinDenomination::TEN_CENTS, CoinDenomination::ONE_UNIT)
            ->withChangeReserve([50 => 20, 10 => 1])
            ->build();

        self::assertFalse($machine->requiresExactChange());
    }
}
