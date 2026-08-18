<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoinDenominationTest extends TestCase
{
    public function test_the_machine_accepts_exactly_four_denominations(): void
    {
        $acceptedCents = array_map(
            static fn (CoinDenomination $denomination): int => $denomination->value,
            CoinDenomination::cases(),
        );

        self::assertSame([5, 10, 25, 100], $acceptedCents);
    }

    #[DataProvider('acceptedCents')]
    public function test_it_is_created_from_an_accepted_cent_value(int $cents): void
    {
        self::assertSame($cents, CoinDenomination::fromCents($cents)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function acceptedCents(): iterable
    {
        yield '0.05' => [5];
        yield '0.10' => [10];
        yield '0.25' => [25];
        yield '1.00' => [100];
    }

    #[DataProvider('rejectedCents')]
    public function test_it_rejects_a_coin_the_machine_does_not_accept(int $cents): void
    {
        $this->expectException(UnsupportedCoin::class);

        CoinDenomination::fromCents($cents);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectedCents(): iterable
    {
        yield 'one cent' => [1];
        yield 'two cents' => [2];
        yield 'twenty cents' => [20];
        yield 'fifty cents' => [50];
        yield 'two units' => [200];
        yield 'zero' => [0];
        yield 'negative' => [-25];
    }

    public function test_an_unsupported_coin_is_a_vending_machine_error(): void
    {
        $this->expectException(VendingMachineError::class);

        CoinDenomination::fromCents(3);
    }

    public function test_the_rejected_value_is_reported_in_the_message(): void
    {
        $this->expectExceptionMessage('20');

        CoinDenomination::fromCents(20);
    }

    /**
     * The spec accepts four coins but lists only three valid coin responses.
     * Example 3 confirms it: 1.00 - 0.65 is returned as 0.25 + 0.10, never
     * as a 1.00 coin.
     */
    public function test_the_one_unit_coin_is_never_dispensed_as_change(): void
    {
        self::assertFalse(CoinDenomination::ONE_UNIT->isDispensableAsChange());
    }

    #[DataProvider('dispensableDenominations')]
    public function test_the_small_coins_are_dispensable_as_change(CoinDenomination $denomination): void
    {
        self::assertTrue($denomination->isDispensableAsChange());
    }

    /**
     * @return iterable<string, array{CoinDenomination}>
     */
    public static function dispensableDenominations(): iterable
    {
        yield '0.05' => [CoinDenomination::FIVE_CENTS];
        yield '0.10' => [CoinDenomination::TEN_CENTS];
        yield '0.25' => [CoinDenomination::TWENTY_FIVE_CENTS];
    }

    public function test_it_exposes_its_amount_as_money(): void
    {
        self::assertSame(25, CoinDenomination::TWENTY_FIVE_CENTS->amount()->cents());
        self::assertSame('0.25', CoinDenomination::TWENTY_FIVE_CENTS->amount()->toDecimalString());
    }

    public function test_denominations_are_ordered_from_smallest_to_largest(): void
    {
        $values = array_map(
            static fn (CoinDenomination $denomination): int => $denomination->value,
            CoinDenomination::cases(),
        );
        $sorted = $values;
        sort($sorted);

        self::assertSame($sorted, $values, 'Change selection relies on a predictable declaration order.');
    }
}
