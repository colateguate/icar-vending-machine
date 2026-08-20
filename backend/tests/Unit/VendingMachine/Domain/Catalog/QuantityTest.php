<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_it_counts_units(): void
    {
        self::assertSame(10, Quantity::of(10)->units());
    }

    public function test_zero_counts_nothing(): void
    {
        self::assertSame(0, Quantity::zero()->units());
        self::assertTrue(Quantity::zero()->isZero());
    }

    public function test_a_positive_quantity_is_not_zero(): void
    {
        self::assertFalse(Quantity::of(1)->isZero());
    }

    public function test_it_rejects_a_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        Quantity::of(-1);
    }

    public function test_it_decrements_by_one(): void
    {
        self::assertSame(9, Quantity::of(10)->decrement()->units());
    }

    public function test_decrementing_the_last_unit_reaches_zero(): void
    {
        self::assertTrue(Quantity::of(1)->decrement()->isZero());
    }

    public function test_it_refuses_to_decrement_below_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Names the guard that must fire, so removing it cannot be masked by
        // the constructor rejecting the negative result instead.
        $this->expectExceptionMessage('no units left');

        Quantity::zero()->decrement();
    }

    public function test_decrementing_below_zero_is_a_bug_not_a_business_error(): void
    {
        try {
            Quantity::zero()->decrement();
            self::fail('Expected the guard to refuse.');
        } catch (InvalidArgumentException $guard) {
            self::assertNotInstanceOf(
                VendingMachineError::class,
                $guard,
                'Running out of stock is a business error the aggregate reports; going below zero is our bug.',
            );
        }
    }

    public function test_decrementing_leaves_the_original_untouched(): void
    {
        $original = Quantity::of(3);

        $original->decrement();

        self::assertSame(3, $original->units());
    }

    public function test_quantities_of_the_same_size_are_equal(): void
    {
        self::assertTrue(Quantity::of(4)->equals(Quantity::of(4)));
        self::assertFalse(Quantity::of(4)->equals(Quantity::of(5)));
    }
}
