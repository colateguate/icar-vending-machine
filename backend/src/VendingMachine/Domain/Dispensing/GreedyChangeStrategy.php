<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Dispensing;

use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

/**
 * Takes the largest coin that still fits, as many times as it can, then moves
 * down. The obvious approach, and the wrong default here.
 *
 * Greedy is provably optimal only for an *unconstrained canonical* coin
 * system. This machine's reserve is finite, which breaks the first
 * precondition: owing 0.30 with one quarter and three dimes, it commits to the
 * quarter, is left owing 0.05 it does not hold, and refuses a sale it had the
 * coins to serve. The second precondition is a data-dependent property too —
 * add a 20-cent coin and 40 becomes 25+10+5 instead of 20+20.
 *
 * Kept, wired to nothing, and covered by a test named after its failure. It is
 * the counterexample that makes OptimalChangeStrategy a correctness decision
 * rather than a preference, and deleting it would delete the argument.
 */
final class GreedyChangeStrategy implements ChangeStrategy
{
    public function selectCoins(Money $amount, CoinCollection $available): CoinCollection
    {
        $pool = $available->dispensableOnly();
        $owed = $amount->cents();
        $selected = CoinCollection::empty();

        foreach (self::largestFirst() as $denomination) {
            $taken = min(intdiv($owed, $denomination->value), $pool->countOf($denomination));

            for ($coin = 0; $coin < $taken; ++$coin) {
                $selected = $selected->add($denomination);
            }

            $owed -= $taken * $denomination->value;
        }

        if (0 !== $owed) {
            throw CannotDispenseChange::forAmount($amount);
        }

        return $selected;
    }

    /**
     * Every denomination, largest first. Filtering out the ones the machine may
     * not dispense would be redundant: the pool was already narrowed by
     * dispensableOnly(), so those denominations hold no coins and the loop can
     * take none of them. One filter, in one place.
     *
     * @return list<CoinDenomination>
     */
    private static function largestFirst(): array
    {
        return array_reverse(CoinDenomination::cases());
    }
}
