<?php

declare(strict_types=1);

namespace App\Tests\Support\Contract;

use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * What every change strategy must promise, whatever algorithm it uses.
 *
 * What is deliberately NOT here: completeness. A strategy is allowed to refuse
 * a sale it could theoretically have served — greedy does exactly that when
 * coins are scarce — so "never refuses when a combination exists" belongs to
 * the strategy that actually guarantees it.
 */
abstract class ChangeStrategyContract extends TestCase
{
    private const SEED = 20260819;

    abstract protected function strategy(): ChangeStrategy;

    final public function test_owing_nothing_selects_no_coins(): void
    {
        $change = $this->strategy()->selectCoins(Money::zero(), self::aHealthyReserve());

        self::assertTrue($change->isEmpty());
    }

    final public function test_it_pays_the_exact_amount(): void
    {
        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.35'), self::aHealthyReserve());

        self::assertTrue($change->total()->equals(Money::fromDecimalString('0.35')));
    }

    final public function test_it_never_hands_back_a_coin_it_was_not_offered(): void
    {
        $available = CoinCollection::fromCounts([25 => 1, 10 => 1]);

        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.35'), $available);

        self::assertSame(0, $change->countOf(CoinDenomination::FIVE_CENTS));
        self::assertTrue(self::isSubsetOf($change, $available));
    }

    /**
     * The brief accepts a 1.00 coin but never lists it as a valid response.
     * Filtering happens inside the strategy: leaving it to the caller would
     * make this a convention rather than a guarantee of the port.
     */
    final public function test_it_never_hands_back_the_one_unit_coin(): void
    {
        $available = CoinCollection::fromCounts([100 => 5, 25 => 2, 10 => 1]);

        $change = $this->strategy()->selectCoins(Money::fromDecimalString('0.60'), $available);

        self::assertSame(0, $change->countOf(CoinDenomination::ONE_UNIT));
        self::assertTrue($change->total()->equals(Money::fromDecimalString('0.60')));
    }

    final public function test_a_reserve_of_only_one_unit_coins_can_pay_nothing(): void
    {
        $this->expectException(CannotDispenseChange::class);

        $this->strategy()->selectCoins(Money::fromDecimalString('0.05'), CoinCollection::fromCounts([100 => 10]));
    }

    final public function test_it_refuses_an_amount_no_combination_can_reach(): void
    {
        $this->expectException(CannotDispenseChange::class);

        // Three cents cannot be built from 5, 10 and 25 however many there are.
        $this->strategy()->selectCoins(Money::fromCents(3), self::aHealthyReserve());
    }

    final public function test_the_refusal_carries_the_amount_still_owed(): void
    {
        try {
            $this->strategy()->selectCoins(Money::fromCents(3), self::aHealthyReserve());
            self::fail('Expected the strategy to refuse.');
        } catch (CannotDispenseChange $refusal) {
            self::assertTrue(
                $refusal->amountDue()->equals(Money::fromCents(3)),
                'the edge reports this amount to the customer as changeDue',
            );
            self::assertStringContainsString('0.03', $refusal->getMessage());
        }
    }

    final public function test_it_refuses_rather_than_paying_out_an_empty_reserve(): void
    {
        $this->expectException(CannotDispenseChange::class);

        $this->strategy()->selectCoins(Money::fromDecimalString('0.05'), CoinCollection::empty());
    }

    /**
     * A randomised sweep with a fixed seed: whatever the algorithm decides to
     * hand out, these three properties must hold every single time.
     */
    final public function test_whatever_it_selects_honours_the_ports_promises(): void
    {
        mt_srand(self::SEED);
        $strategy = $this->strategy();
        $servedCases = 0;

        for ($case = 0; $case < 250; ++$case) {
            $available = CoinCollection::fromCounts([
                5 => mt_rand(0, 6),
                10 => mt_rand(0, 6),
                25 => mt_rand(0, 4),
                100 => mt_rand(0, 2),
            ]);
            // Positive amounts only: owing nothing has its own dedicated test
            // and would pass the three assertions below trivially, padding the
            // guard with cases that exercise no selection logic.
            $amount = Money::fromCents(5 * mt_rand(1, 20));

            try {
                $change = $strategy->selectCoins($amount, $available);
            } catch (CannotDispenseChange) {
                continue;
            }

            ++$servedCases;
            self::assertTrue($change->total()->equals($amount), 'the selection must add up to the amount owed');
            self::assertTrue(self::isSubsetOf($change, $available), 'it cannot hand out coins it does not hold');
            self::assertSame(0, $change->countOf(CoinDenomination::ONE_UNIT), 'the 1.00 coin is never change');
        }

        self::assertGreaterThan(
            100,
            $servedCases,
            'a sweep where almost everything is refused would assert nothing',
        );
    }

    final protected static function aHealthyReserve(): CoinCollection
    {
        return CoinCollection::fromCounts([5 => 10, 10 => 10, 25 => 10]);
    }

    final protected static function isSubsetOf(CoinCollection $part, CoinCollection $whole): bool
    {
        foreach ($part->toArray() as $value => $count) {
            if ($count > ($whole->toArray()[$value] ?? 0)) {
                return false;
            }
        }

        return true;
    }
}
