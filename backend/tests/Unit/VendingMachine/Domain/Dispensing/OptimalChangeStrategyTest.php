<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Dispensing;

use App\Tests\Support\Contract\ChangeStrategyContract;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

final class OptimalChangeStrategyTest extends ChangeStrategyContract
{
    private const SEED = 20260819;

    /** @var list<int> the denominations the machine may hand back, ascending */
    private const DISPENSABLE = [5, 10, 25];

    protected function strategy(): ChangeStrategy
    {
        return new OptimalChangeStrategy();
    }

    public function test_it_serves_the_sale_greedy_refuses(): void
    {
        $change = $this->strategy()->selectCoins(
            Money::fromDecimalString('0.30'),
            CoinCollection::fromCounts([25 => 1, 10 => 3]),
        );

        self::assertTrue($change->equals(CoinCollection::fromCounts([10 => 3])));
    }

    public function test_it_uses_as_few_coins_as_the_reserve_allows(): void
    {
        $change = $this->strategy()->selectCoins(
            Money::fromDecimalString('0.30'),
            CoinCollection::fromCounts([25 => 1, 10 => 3, 5 => 6]),
        );

        self::assertTrue(
            $change->equals(CoinCollection::fromCounts([25 => 1, 5 => 1])),
            'a quarter and a nickel beats three dimes: two coins instead of three',
        );
    }

    public function test_it_spends_the_last_coins_it_has_when_that_is_the_only_way(): void
    {
        $available = CoinCollection::fromCounts([25 => 1, 10 => 1, 5 => 1]);

        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.40'), $available);

        self::assertTrue($change->equals($available), 'the exact amount needs every coin in the machine');
    }

    /**
     * The property that justifies choosing this implementation as the default:
     * it must never refuse a sale it had the coins to serve. Checked against an
     * exhaustive search over the same reserve — a slow oracle is fine when it
     * is only ever used to judge the fast one.
     */
    public function test_it_never_refuses_when_some_combination_would_work(): void
    {
        mt_srand(self::SEED);
        $strategy = $this->strategy();
        $solvableCases = 0;

        for ($case = 0; $case < 300; ++$case) {
            $counts = [5 => mt_rand(0, 5), 10 => mt_rand(0, 5), 25 => mt_rand(0, 3)];
            $target = 5 * mt_rand(0, 16);

            if (!self::someCombinationReaches($target, $counts)) {
                continue;
            }

            ++$solvableCases;
            $change = $strategy->selectCoins(Money::fromCents($target), CoinCollection::fromCounts($counts));
            self::assertSame(
                $target,
                $change->total()->cents(),
                \sprintf('failed to pay %d cents from %s', $target, json_encode($counts)),
            );
        }

        self::assertGreaterThan(150, $solvableCases, 'the oracle must actually find solvable cases to judge');
    }

    public function test_the_oracle_and_the_strategy_agree_on_refusals(): void
    {
        mt_srand(self::SEED + 1);
        $strategy = $this->strategy();
        $refusedCases = 0;

        for ($case = 0; $case < 300; ++$case) {
            $counts = [5 => mt_rand(0, 3), 10 => mt_rand(0, 3), 25 => mt_rand(0, 2)];
            $target = mt_rand(0, 90);

            if (self::someCombinationReaches($target, $counts)) {
                continue;
            }

            ++$refusedCases;
            try {
                $strategy->selectCoins(Money::fromCents($target), CoinCollection::fromCounts($counts));
                self::fail(\sprintf('paid %d cents from %s, which is impossible', $target, json_encode($counts)));
            } catch (CannotDispenseChange) {
                // expected: no combination exists
            }
        }

        self::assertGreaterThan(50, $refusedCases, 'the sweep must actually reach impossible amounts');
    }

    public function test_a_one_unit_coin_in_the_reserve_never_helps(): void
    {
        $this->expectException(CannotDispenseChange::class);

        // 0.95 is reachable only by adding the 1.00 coin's worth, which is off
        // limits, and the small coins fall short.
        $this->strategy()->selectCoins(
            Money::fromDecimalString('0.95'),
            CoinCollection::fromCounts([100 => 3, 25 => 1, 10 => 1]),
        );
    }

    public function test_it_ignores_denominations_it_holds_none_of(): void
    {
        $change = $this->strategy()->selectCoins(
            Money::fromDecimalString('0.50'),
            CoinCollection::fromCounts([25 => 2, 10 => 0, 5 => 0]),
        );

        self::assertSame(2, $change->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    /**
     * The property the class is named after, checked against the oracle rather
     * than against hand-picked cases. Fixed examples are not enough here: an
     * update rule that simply kept the last candidate instead of the best one
     * still produces the minimal answer for several obvious reserves, so only a
     * sweep against an independent minimum catches it.
     */
    public function test_it_uses_no_more_coins_than_an_exhaustive_search_finds(): void
    {
        mt_srand(self::SEED + 2);
        $strategy = $this->strategy();
        $comparedCases = 0;

        for ($case = 0; $case < 300; ++$case) {
            $counts = [5 => mt_rand(0, 5), 10 => mt_rand(0, 5), 25 => mt_rand(0, 3)];
            $target = 5 * mt_rand(1, 16);

            $fewestPossible = self::fewestCoinsThatReach($target, $counts);
            if (null === $fewestPossible) {
                continue;
            }

            ++$comparedCases;
            $change = $strategy->selectCoins(Money::fromCents($target), CoinCollection::fromCounts($counts));

            self::assertSame(
                $fewestPossible,
                array_sum($change->toArray()),
                \sprintf('paying %d cents from %s took more coins than necessary', $target, json_encode($counts)),
            );
        }

        self::assertGreaterThan(150, $comparedCases, 'the oracle must find enough payable cases to judge');
    }

    /**
     * Exhaustive search for the smallest number of coins that reaches the
     * amount, or null when nothing does — the independent answer to both "was
     * this payable at all?" and "could it have been done with fewer?".
     *
     * Backtracking rather than a table on purpose: an oracle sharing the
     * strategy's structure could be wrong in the same way and agree with it.
     * The one thing both share is the dispensable set, hardcoded above — if
     * CoinDenomination::isDispensableAsChange() ever changes, DISPENSABLE must
     * change with it or these sweeps start comparing different problems.
     *
     * @param array<int, int> $counts denomination value in cents => how many
     */
    private static function fewestCoinsThatReach(int $target, array $counts, int $index = 0): ?int
    {
        if (0 === $target) {
            return 0;
        }

        if ($target < 0 || $index >= \count(self::DISPENSABLE)) {
            return null;
        }

        $value = self::DISPENSABLE[$index];
        $fewest = null;

        for ($used = 0; $used <= ($counts[$value] ?? 0); ++$used) {
            $rest = self::fewestCoinsThatReach($target - $used * $value, $counts, $index + 1);

            if (null !== $rest && (null === $fewest || $rest + $used < $fewest)) {
                $fewest = $rest + $used;
            }
        }

        return $fewest;
    }

    /**
     * @param array<int, int> $counts
     */
    private static function someCombinationReaches(int $target, array $counts): bool
    {
        return null !== self::fewestCoinsThatReach($target, $counts);
    }
}
