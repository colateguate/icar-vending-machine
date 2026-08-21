<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\UnsupportedCoin;

/**
 * The coins the acceptor can read, valued in cents.
 *
 * A backed enum rather than a wrapper object: what the hardware understands is
 * closed, finite and known at compile time, so `tryFrom()` gives validation for
 * free, `cases()` gives exhaustiveness, and static analysis narrows every
 * `match`.
 *
 * This is deliberately not the same question as which coins a given machine
 * takes today — that one varies per machine and per service visit, so it is
 * state (AcceptedCoins) rather than a type. Reading a coin and taking it are
 * two different things, and only the first is a property of the hardware.
 *
 * The two smallest coins of the currency are absent because no vending machine
 * takes them, which is a fact about the machine rather than about the money.
 *
 * Cases are declared smallest to largest; change selection relies on that
 * order being meaningful.
 */
enum CoinDenomination: int
{
    case FIVE_CENTS = 5;
    case TEN_CENTS = 10;
    case TWENTY_FIVE_CENTS = 25;
    case FIFTY_CENTS = 50;
    case ONE_UNIT = 100;
    case TWO_UNITS = 200;

    public static function fromCents(int $cents): self
    {
        return self::tryFrom($cents) ?? throw UnsupportedCoin::ofCents($cents);
    }

    /**
     * The spec accepts four coins but lists only three valid coin responses:
     * the machine takes a 1.00 coin and never gives one back. Example 3 in the
     * brief confirms it — 1.00 for a 0.65 item returns 0.25 + 0.10.
     *
     * The rule the brief states for the 1.00 is the rule this reads one coin
     * further up: the big pieces go in and do not come back, and 2.00 is the
     * one the brief would have named next. The 0.50 sits on the other side of
     * that line, where every real machine puts it.
     *
     * Written as an exhaustive match on purpose: adding a denomination makes
     * static analysis demand an explicit answer here instead of silently
     * inheriting a default. That is not a hypothetical — it is how the two
     * denominations above this comment got their answer.
     */
    public function isDispensableAsChange(): bool
    {
        return match ($this) {
            self::FIVE_CENTS, self::TEN_CENTS, self::TWENTY_FIVE_CENTS, self::FIFTY_CENTS => true,
            self::ONE_UNIT, self::TWO_UNITS => false,
        };
    }

    public function amount(): Money
    {
        return Money::fromCents($this->value);
    }
}
