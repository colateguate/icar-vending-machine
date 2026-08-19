<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Dispensing;

use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

/**
 * Bounded-coin dynamic programming: considers every way the reserve could add
 * up to the amount owed, and only refuses when no way exists.
 *
 * The property that makes this the default is completeness, not elegance. It
 * cannot lose a sale the machine had the coins to serve, which is exactly what
 * greedy does once coins run scarce. Using the fewest coins is a side effect of
 * how the table is scored, pleasant but secondary — and it happens to spend
 * large coins first, leaving the small ones that future change depends on.
 *
 * Owing nothing needs no special case: the table resolves a zero amount to an
 * empty selection on its own, and an early return for it would be a branch no
 * test could tell apart from the general path.
 *
 * Cost is O(denominations x amount x count-per-denomination). That is fine for
 * the physical shape of the problem: three dispensable denominations, change
 * under a couple of units, a reserve of tens of coins. If a machine ever held
 * thousands of coins the counts could be binary-split, which is a real
 * optimisation for a problem this one does not have.
 */
final class OptimalChangeStrategy implements ChangeStrategy
{
    public function selectCoins(Money $amount, CoinCollection $available): CoinCollection
    {
        $owed = $amount->cents();
        $pool = $available->dispensableOnly();
        $denominations = CoinDenomination::cases();

        ['fewestCoins' => $fewestCoins, 'taken' => $taken] = self::scoreEverySubtotal($owed, $denominations, $pool);

        if (!isset($fewestCoins[\count($denominations)][$owed])) {
            throw CannotDispenseChange::forAmount($amount);
        }

        return self::rebuildSelection($denominations, $taken, $owed);
    }

    /**
     * Fills the table the decision is read from.
     *
     * fewestCoins[step][subtotal] is the cheapest way to build subtotal using
     * only the first `step` denominations. A subtotal simply absent from a row
     * means no combination reaches it — absence states that better than a
     * sentinel value, which would need an argument about why the chosen number
     * can never be mistaken for a real coin count.
     *
     * taken[step][subtotal] remembers how many coins of that denomination the
     * answer used. Scoring alone would only prove a solution exists, and the
     * machine has to hand over actual coins.
     *
     * A note for anyone changing the arithmetic here: with today's coin set the
     * comparison below cannot be observed from outside — the coin count is
     * monotone in how many of the current denomination are taken, so the last
     * candidate is always the best one. It stops being true the moment a
     * non-canonical denomination is added, which is exactly why the comparison
     * is written rather than assumed. Several mutators are muted for this
     * method in infection.json5 for that reason, with the expiry condition
     * spelled out there.
     *
     * @param list<CoinDenomination> $denominations
     *
     * @return array{fewestCoins: array<int, array<int, int>>, taken: array<int, array<int, int>>}
     */
    private static function scoreEverySubtotal(int $owed, array $denominations, CoinCollection $pool): array
    {
        $fewestCoins = [0 => [0 => 0]];
        $taken = [];

        foreach ($denominations as $step => $denomination) {
            $value = $denomination->value;
            $inReserve = $pool->countOf($denomination);

            $fewestCoins[$step + 1] = [];
            $taken[$step] = [];

            for ($subtotal = 0; $subtotal <= $owed; ++$subtotal) {
                for ($coins = 0; $coins <= $inReserve && $coins * $value <= $subtotal; ++$coins) {
                    $withoutThisDenomination = $fewestCoins[$step][$subtotal - $coins * $value] ?? null;
                    $bestSoFar = $fewestCoins[$step + 1][$subtotal] ?? null;

                    // One decision, stated once: take this many coins if the
                    // rest of the subtotal was buildable and doing so beats
                    // whatever answer we already had.
                    if (null !== $withoutThisDenomination
                        && (null === $bestSoFar || $withoutThisDenomination + $coins < $bestSoFar)) {
                        $fewestCoins[$step + 1][$subtotal] = $withoutThisDenomination + $coins;
                        $taken[$step][$subtotal] = $coins;
                    }
                }
            }
        }

        return ['fewestCoins' => $fewestCoins, 'taken' => $taken];
    }

    /**
     * Reads the remembered choices in reverse step order. The table was filled
     * forwards, so each denomination's recorded choice is only meaningful once
     * every later denomination has already been settled — walking forwards
     * would consume decisions before knowing which subtotal they applied to.
     *
     * @param list<CoinDenomination>      $denominations
     * @param array<int, array<int, int>> $taken
     */
    private static function rebuildSelection(array $denominations, array $taken, int $owed): CoinCollection
    {
        $selected = CoinCollection::empty();
        $remaining = $owed;

        for ($step = \count($denominations) - 1; $step >= 0; --$step) {
            $coins = $taken[$step][$remaining] ?? 0;

            for ($coin = 0; $coin < $coins; ++$coin) {
                $selected = $selected->add($denominations[$step]);
            }

            $remaining -= $coins * $denominations[$step]->value;
        }

        return $selected;
    }
}
