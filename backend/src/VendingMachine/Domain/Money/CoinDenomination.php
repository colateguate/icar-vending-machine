<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\UnsupportedCoin;

/**
 * The coins this machine accepts, valued in cents.
 *
 * A backed enum rather than a wrapper object: the accepted set is closed,
 * finite and known at compile time, so `tryFrom()` gives validation for free,
 * `cases()` gives exhaustiveness, and static analysis narrows every `match`.
 *
 * Cases are declared smallest to largest; change selection relies on that
 * order being meaningful.
 */
enum CoinDenomination: int
{
    case FIVE_CENTS = 5;
    case TEN_CENTS = 10;
    case TWENTY_FIVE_CENTS = 25;
    case ONE_UNIT = 100;

    public static function fromCents(int $cents): self
    {
        return self::tryFrom($cents) ?? throw UnsupportedCoin::ofCents($cents);
    }

    /**
     * The spec accepts four coins but lists only three valid coin responses:
     * the machine takes a 1.00 coin and never gives one back. Example 3 in the
     * brief confirms it — 1.00 for a 0.65 item returns 0.25 + 0.10.
     *
     * Written as an exhaustive match on purpose: adding a denomination makes
     * static analysis demand an explicit answer here instead of silently
     * inheriting a default.
     */
    public function isDispensableAsChange(): bool
    {
        return match ($this) {
            self::FIVE_CENTS, self::TEN_CENTS, self::TWENTY_FIVE_CENTS => true,
            self::ONE_UNIT => false,
        };
    }

    public function amount(): Money
    {
        return Money::fromCents($this->value);
    }
}
