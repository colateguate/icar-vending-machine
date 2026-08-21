<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

/**
 * Which coins this machine's slot takes — as opposed to which coins the
 * hardware can read at all, which is CoinDenomination's question.
 */
final class AcceptedCoinsTest extends TestCase
{
    public function test_it_accepts_what_it_was_given(): void
    {
        $accepted = AcceptedCoins::of(CoinDenomination::FIVE_CENTS, CoinDenomination::ONE_UNIT);

        self::assertTrue($accepted->accepts(CoinDenomination::FIVE_CENTS));
        self::assertTrue($accepted->accepts(CoinDenomination::ONE_UNIT));
    }

    public function test_it_refuses_a_denomination_it_was_not_given(): void
    {
        $accepted = AcceptedCoins::of(CoinDenomination::FIVE_CENTS);

        self::assertFalse($accepted->accepts(CoinDenomination::FIFTY_CENTS));
    }

    /**
     * The state the whole feature exists to make representable: a machine that
     * takes nothing is out of service, not a broken machine. Nobody can pay it,
     * so nobody can buy from it, and that falls out of the model rather than
     * needing a flag of its own.
     */
    public function test_a_machine_may_accept_nothing_at_all(): void
    {
        $accepted = AcceptedCoins::none();

        self::assertTrue($accepted->isEmpty());
        self::assertFalse($accepted->accepts(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame([], $accepted->all());
    }

    public function test_naming_a_denomination_twice_accepts_it_once(): void
    {
        $accepted = AcceptedCoins::of(
            CoinDenomination::TEN_CENTS,
            CoinDenomination::TEN_CENTS,
        );

        self::assertSame([CoinDenomination::TEN_CENTS], $accepted->all());
    }

    /**
     * Kept in declaration order so that two sets built from the same
     * denominations compare equal whatever order they arrived in, and so the
     * list a client is shown is stable.
     */
    public function test_it_is_ordered_from_smallest_to_largest(): void
    {
        $accepted = AcceptedCoins::of(
            CoinDenomination::ONE_UNIT,
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::TWENTY_FIVE_CENTS,
        );

        self::assertSame(
            [CoinDenomination::FIVE_CENTS, CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::ONE_UNIT],
            $accepted->all(),
        );
    }

    public function test_two_sets_of_the_same_denominations_are_equal(): void
    {
        $one = AcceptedCoins::of(CoinDenomination::FIVE_CENTS, CoinDenomination::ONE_UNIT);
        $other = AcceptedCoins::of(CoinDenomination::ONE_UNIT, CoinDenomination::FIVE_CENTS);

        self::assertTrue($one->equals($other));
    }

    public function test_sets_differing_in_one_denomination_are_not_equal(): void
    {
        $one = AcceptedCoins::of(CoinDenomination::FIVE_CENTS, CoinDenomination::ONE_UNIT);
        $other = AcceptedCoins::of(CoinDenomination::FIVE_CENTS);

        self::assertFalse($one->equals($other));
        self::assertFalse($other->equals(AcceptedCoins::none()));
    }
}
