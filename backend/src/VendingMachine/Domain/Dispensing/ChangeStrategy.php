<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Dispensing;

use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;

/**
 * How the machine decides which coins to hand back.
 *
 * A domain service rather than a method on CoinCollection: the decision needs
 * both the amount owed and what is physically available, and there is more
 * than one defensible answer — which is the other reason it is an interface.
 *
 * Every implementation promises, without exception:
 *   - the coins returned add up to exactly the amount owed;
 *   - they are a subset of what the machine holds;
 *   - none of them is a denomination the machine may not dispense (the 1.00
 *     coin is accepted but never returned). Implementations filter this
 *     themselves; leaving it to the caller would make it a convention rather
 *     than a guarantee.
 *
 * What implementations do NOT all promise is completeness: refusing a sale
 * that some combination could have served is allowed, and greedy does it.
 */
interface ChangeStrategy
{
    /**
     * @throws CannotDispenseChange when this strategy cannot compose the amount
     */
    public function selectCoins(Money $amount, CoinCollection $available): CoinCollection;
}
