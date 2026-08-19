<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Dispensing;

use App\Tests\Support\Contract\ChangeStrategyContract;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\GreedyChangeStrategy;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

final class GreedyChangeStrategyTest extends ChangeStrategyContract
{
    protected function strategy(): ChangeStrategy
    {
        return new GreedyChangeStrategy();
    }

    /**
     * The reason the ChangeStrategy port exists at all.
     *
     * Greedy is provably optimal only for an unconstrained canonical coin
     * system. Neither precondition holds in a vending machine: the reserve is
     * finite. Owing 0.30 with one quarter and three dimes, greedy commits to
     * the quarter, is left owing 0.05 it does not have, and refuses a sale it
     * had the coins to serve. That is lost revenue caused by an algorithm
     * choice, which is what turns the interface from a flexibility flourish
     * into a correctness requirement.
     */
    public function test_greedy_refuses_a_sale_the_optimal_strategy_serves(): void
    {
        $amount = Money::fromDecimalString('0.30');
        $available = CoinCollection::fromCounts([25 => 1, 10 => 3]);

        $servedByOptimal = (new OptimalChangeStrategy())->selectCoins($amount, $available);
        self::assertTrue(
            $servedByOptimal->equals(CoinCollection::fromCounts([10 => 3])),
            'three dimes make exactly 0.30 out of what the machine holds',
        );

        $this->expectException(CannotDispenseChange::class);
        (new GreedyChangeStrategy())->selectCoins($amount, $available);
    }

    public function test_it_takes_the_largest_denomination_that_still_fits(): void
    {
        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.40'), self::aHealthyReserve());

        self::assertTrue(
            $change->equals(CoinCollection::fromCounts([25 => 1, 10 => 1, 5 => 1])),
            'descending order: a quarter, then a dime, then a nickel',
        );
    }

    /**
     * Example 3 from the brief: 1.00 for a 0.65 item comes back as 0.25 + 0.10.
     * Greedy happens to get this one right, which is precisely why the failure
     * above is easy to miss.
     */
    public function test_it_serves_the_change_of_example_3(): void
    {
        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.35'), self::aHealthyReserve());

        self::assertTrue($change->equals(CoinCollection::fromCounts([25 => 1, 10 => 1])));
    }

    public function test_it_falls_back_to_smaller_coins_when_the_large_ones_run_out(): void
    {
        $change = $this->strategy()->selectCoins(
            Money::fromDecimalString('0.30'),
            CoinCollection::fromCounts([25 => 0, 10 => 2, 5 => 2]),
        );

        self::assertTrue($change->equals(CoinCollection::fromCounts([10 => 2, 5 => 2])));
    }

    public function test_it_pays_a_whole_unit_with_smaller_coins(): void
    {
        $change = $this->strategy()->selectCoins(Money::fromDecimalString('1.00'), self::aHealthyReserve());

        self::assertTrue($change->total()->equals(Money::fromDecimalString('1.00')));
        self::assertSame(0, $change->countOf(CoinDenomination::ONE_UNIT));
    }
}
