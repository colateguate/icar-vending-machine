<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_is_created_from_cents(): void
    {
        self::assertSame(65, Money::fromCents(65)->cents());
    }

    public function test_zero_holds_no_amount(): void
    {
        self::assertTrue(Money::zero()->isZero());
        self::assertSame(0, Money::zero()->cents());
    }

    public function test_a_non_zero_amount_is_not_zero(): void
    {
        self::assertFalse(Money::fromCents(5)->isZero());
    }

    public function test_it_rejects_a_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(-1);
    }

    /**
     * Two distinct failure families: what the caller sent us wrong is a
     * VendingMachineError the edge maps to 4xx; a broken invariant is a bug
     * and must NOT be dressed up as a business error.
     */
    public function test_a_broken_invariant_is_not_a_user_facing_error(): void
    {
        try {
            Money::fromCents(-1);
            self::fail('Expected the guard to reject a negative amount.');
        } catch (InvalidArgumentException $guard) {
            self::assertNotInstanceOf(VendingMachineError::class, $guard);
        }
    }

    /**
     * @param non-empty-string $input
     */
    #[DataProvider('wellFormedDecimalStrings')]
    public function test_it_parses_a_decimal_string(string $input, int $expectedCents): void
    {
        self::assertSame($expectedCents, Money::fromDecimalString($input)->cents());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function wellFormedDecimalStrings(): iterable
    {
        yield 'water price' => ['0.65', 65];
        yield 'juice price' => ['1.00', 100];
        yield 'soda price' => ['1.50', 150];
        yield 'smallest coin' => ['0.05', 5];
        yield 'no decimal part' => ['1', 100];
        yield 'single decimal digit' => ['0.5', 50];
        yield 'zero' => ['0', 0];
    }

    #[DataProvider('malformedDecimalStrings')]
    public function test_it_rejects_a_malformed_decimal_string(string $input): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        Money::fromDecimalString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDecimalStrings(): iterable
    {
        yield 'not a number' => ['abc'];
        yield 'empty' => [''];
        yield 'negative' => ['-1.00'];
        yield 'three decimals' => ['1.234'];
        yield 'comma separator' => ['1,50'];
        yield 'surrounding spaces' => [' 1.00 '];
        yield 'trailing dot' => ['1.'];
        yield 'leading dot' => ['.65'];
    }

    public function test_a_malformed_amount_is_a_vending_machine_error(): void
    {
        $this->expectException(VendingMachineError::class);

        Money::fromDecimalString('nope');
    }

    #[DataProvider('decimalRenderings')]
    public function test_it_renders_as_a_decimal_string(int $cents, string $expected): void
    {
        self::assertSame($expected, Money::fromCents($cents)->toDecimalString());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function decimalRenderings(): iterable
    {
        yield 'zero always shows two decimals' => [0, '0.00'];
        yield 'cents only' => [5, '0.05'];
        yield 'water price' => [65, '0.65'];
        yield 'whole unit' => [100, '1.00'];
        yield 'soda price' => [150, '1.50'];
        yield 'large amount' => [12345, '123.45'];
    }

    public function test_it_adds_another_amount(): void
    {
        $result = Money::fromCents(65)->add(Money::fromCents(35));

        self::assertSame(100, $result->cents());
    }

    public function test_it_subtracts_another_amount(): void
    {
        $result = Money::fromCents(100)->subtract(Money::fromCents(65));

        self::assertSame(35, $result->cents());
    }

    public function test_it_refuses_to_subtract_into_a_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(65)->subtract(Money::fromCents(100));
    }

    public function test_it_multiplies_by_a_factor(): void
    {
        self::assertSame(75, Money::fromCents(25)->multiplyBy(3)->cents());
    }

    public function test_multiplying_by_zero_yields_zero(): void
    {
        self::assertTrue(Money::fromCents(25)->multiplyBy(0)->isZero());
    }

    public function test_it_refuses_a_negative_factor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(25)->multiplyBy(-1);
    }

    public function test_amounts_with_the_same_value_are_equal(): void
    {
        self::assertTrue(Money::fromCents(65)->equals(Money::fromDecimalString('0.65')));
        self::assertFalse(Money::fromCents(65)->equals(Money::fromCents(66)));
    }

    public function test_it_compares_amounts(): void
    {
        $water = Money::fromCents(65);
        $soda = Money::fromCents(150);

        self::assertTrue($soda->isGreaterThanOrEqualTo($water));
        self::assertTrue($water->isGreaterThanOrEqualTo($water));
        self::assertFalse($water->isGreaterThanOrEqualTo($soda));

        self::assertTrue($water->isLessThan($soda));
        self::assertFalse($water->isLessThan($water));
    }

    public function test_arithmetic_leaves_the_original_untouched(): void
    {
        $original = Money::fromCents(100);

        $original->add(Money::fromCents(50));
        $original->subtract(Money::fromCents(50));
        $original->multiplyBy(2);

        self::assertSame(100, $original->cents());
    }
}
