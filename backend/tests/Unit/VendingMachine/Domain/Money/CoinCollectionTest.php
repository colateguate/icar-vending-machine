<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CoinCollectionTest extends TestCase
{
    public function test_an_empty_collection_holds_nothing(): void
    {
        $coins = CoinCollection::empty();

        self::assertTrue($coins->isEmpty());
        self::assertTrue($coins->total()->isZero());
        self::assertSame(0, $coins->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    public function test_it_is_built_from_denominations(): void
    {
        $coins = CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        );

        self::assertSame(2, $coins->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(1, $coins->countOf(CoinDenomination::TEN_CENTS));
        self::assertSame(0, $coins->countOf(CoinDenomination::FIVE_CENTS));
        self::assertFalse($coins->isEmpty());
    }

    public function test_it_is_built_from_a_map_of_counts(): void
    {
        $coins = CoinCollection::fromCounts([25 => 2, 10 => 1]);

        self::assertTrue($coins->equals(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        )));
    }

    public function test_a_count_map_rejects_an_unsupported_denomination(): void
    {
        $this->expectException(UnsupportedCoin::class);

        CoinCollection::fromCounts([20 => 1]);
    }

    public function test_a_count_map_rejects_a_negative_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CoinCollection::fromCounts([25 => -1]);
    }

    public function test_adding_a_coin_returns_a_new_collection(): void
    {
        $original = CoinCollection::of(CoinDenomination::TEN_CENTS);

        $increased = $original->add(CoinDenomination::TEN_CENTS);

        self::assertSame(1, $original->countOf(CoinDenomination::TEN_CENTS), 'the original must not change');
        self::assertSame(2, $increased->countOf(CoinDenomination::TEN_CENTS));
    }

    public function test_adding_a_denomination_it_does_not_hold_yet_yields_exactly_one(): void
    {
        $coins = CoinCollection::empty()->add(CoinDenomination::FIVE_CENTS);

        self::assertSame(1, $coins->countOf(CoinDenomination::FIVE_CENTS));
        self::assertFalse($coins->isEmpty());
        self::assertTrue($coins->total()->equals(Money::fromDecimalString('0.05')));
    }

    public function test_it_totals_the_amount_it_holds(): void
    {
        $coins = CoinCollection::of(
            CoinDenomination::ONE_UNIT,
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TWENTY_FIVE_CENTS,
        );

        self::assertTrue($coins->total()->equals(Money::fromDecimalString('1.50')));
    }

    public function test_it_merges_another_collection(): void
    {
        $escrow = CoinCollection::of(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::TEN_CENTS);
        $reserve = CoinCollection::of(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::FIVE_CENTS);

        $pool = $reserve->merge($escrow);

        self::assertSame(2, $pool->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(1, $pool->countOf(CoinDenomination::TEN_CENTS));
        self::assertSame(1, $pool->countOf(CoinDenomination::FIVE_CENTS));
        self::assertSame(1, $reserve->countOf(CoinDenomination::TWENTY_FIVE_CENTS), 'the receiver must not change');
        self::assertSame(0, $reserve->countOf(CoinDenomination::TEN_CENTS), 'the receiver must not change');
    }

    public function test_it_subtracts_another_collection(): void
    {
        $reserve = CoinCollection::fromCounts([25 => 2, 10 => 3]);

        // The 0.35 change of example 3 in the brief, handed back as 0.25 + 0.10.
        $afterDispensingChange = $reserve->subtract(
            CoinCollection::of(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::TEN_CENTS),
        );

        self::assertSame(1, $afterDispensingChange->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(2, $afterDispensingChange->countOf(CoinDenomination::TEN_CENTS));
        self::assertTrue($afterDispensingChange->total()->equals(Money::fromDecimalString('0.45')));
    }

    public function test_subtracting_everything_leaves_it_empty(): void
    {
        $reserve = CoinCollection::fromCounts([25 => 1]);

        self::assertTrue($reserve->subtract(CoinCollection::fromCounts([25 => 1]))->isEmpty());
    }

    public function test_it_refuses_to_subtract_coins_it_does_not_hold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Naming the guard that must fire: without it the shortfall would slip
        // through to the canonicalisation check, which throws the same type
        // with a far less useful message.
        $this->expectExceptionMessage('Cannot take 2 coin(s) of 25 cents from a collection holding 1');

        CoinCollection::fromCounts([25 => 1])->subtract(CoinCollection::fromCounts([25 => 2]));
    }

    public function test_it_refuses_to_subtract_a_denomination_it_never_had(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('from a collection holding 0');

        CoinCollection::fromCounts([25 => 1])->subtract(CoinCollection::fromCounts([5 => 1]));
    }

    public function test_dispensable_only_drops_the_one_unit_coin(): void
    {
        $pool = CoinCollection::fromCounts([100 => 4, 25 => 1, 10 => 2]);

        $dispensable = $pool->dispensableOnly();

        self::assertSame(0, $dispensable->countOf(CoinDenomination::ONE_UNIT));
        self::assertSame(1, $dispensable->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(2, $dispensable->countOf(CoinDenomination::TEN_CENTS));
        self::assertSame(4, $pool->countOf(CoinDenomination::ONE_UNIT), 'the original must not change');
    }

    public function test_collections_holding_the_same_coins_are_equal(): void
    {
        self::assertTrue(
            CoinCollection::fromCounts([25 => 2, 10 => 1])
                ->equals(CoinCollection::fromCounts([10 => 1, 25 => 2])),
            'declaration order must not matter',
        );
        self::assertFalse(
            CoinCollection::fromCounts([25 => 2])->equals(CoinCollection::fromCounts([25 => 1])),
        );
    }

    public function test_an_explicit_zero_count_equals_an_absent_denomination(): void
    {
        self::assertTrue(CoinCollection::fromCounts([25 => 0])->equals(CoinCollection::empty()));
    }

    public function test_it_exposes_its_counts_keyed_by_denomination_value(): void
    {
        $coins = CoinCollection::fromCounts([25 => 2, 5 => 1]);

        self::assertSame([5 => 1, 25 => 2], $coins->toArray());
    }

    public function test_an_empty_collection_exposes_an_empty_map(): void
    {
        self::assertSame([], CoinCollection::empty()->toArray());
    }
}
