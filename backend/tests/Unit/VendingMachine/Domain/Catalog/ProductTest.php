<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function test_it_carries_selector_name_price_and_stock(): void
    {
        $water = self::water(10);

        self::assertTrue($water->selector()->equals(ProductSelector::fromString('WATER')));
        self::assertSame('Water', $water->name());
        self::assertTrue($water->price()->equals(Money::fromDecimalString('0.65')));
        self::assertSame(10, $water->available()->units());
    }

    public function test_it_is_in_stock_while_units_remain(): void
    {
        self::assertFalse(self::water(1)->isOutOfStock());
    }

    public function test_it_is_out_of_stock_at_zero_units(): void
    {
        self::assertTrue(self::water(0)->isOutOfStock());
    }

    public function test_dispensing_one_unit_reduces_the_stock(): void
    {
        $dispensed = self::water(10)->dispenseOne();

        self::assertSame(9, $dispensed->available()->units());
    }

    public function test_dispensing_leaves_the_original_untouched(): void
    {
        $water = self::water(10);

        $water->dispenseOne();

        self::assertSame(10, $water->available()->units());
    }

    public function test_dispensing_preserves_everything_but_the_stock(): void
    {
        $dispensed = self::water(10)->dispenseOne();

        self::assertTrue($dispensed->selector()->equals(ProductSelector::fromString('WATER')));
        self::assertSame('Water', $dispensed->name());
        self::assertTrue($dispensed->price()->equals(Money::fromDecimalString('0.65')));
    }

    public function test_dispensing_the_last_unit_leaves_it_out_of_stock(): void
    {
        self::assertTrue(self::water(1)->dispenseOne()->isOutOfStock());
    }

    public function test_it_refuses_to_dispense_when_out_of_stock(): void
    {
        $this->expectException(ProductOutOfStock::class);
        $this->expectExceptionMessage('WATER');

        self::water(0)->dispenseOne();
    }

    public function test_running_out_of_stock_is_a_business_error(): void
    {
        $this->expectException(VendingMachineError::class);

        self::water(0)->dispenseOne();
    }

    public function test_products_matching_in_every_field_are_equal(): void
    {
        self::assertTrue(self::water(10)->equals(self::water(10)));
        self::assertFalse(self::water(10)->equals(self::water(9)), 'stock is part of the value');
    }

    public function test_products_under_different_selectors_are_not_equal(): void
    {
        $sameProductDifferentButton = new Product(
            ProductSelector::fromString('JUICE'),
            'Water',
            Money::fromDecimalString('0.65'),
            Quantity::of(10),
        );

        self::assertFalse(self::water(10)->equals($sameProductDifferentButton));
    }

    public function test_products_differing_in_name_are_not_equal(): void
    {
        $renamed = new Product(
            ProductSelector::fromString('WATER'),
            'Sparkling Water',
            Money::fromDecimalString('0.65'),
            Quantity::of(10),
        );

        self::assertFalse(self::water(10)->equals($renamed));
    }

    public function test_products_differing_in_price_are_not_equal(): void
    {
        $cheaper = new Product(
            ProductSelector::fromString('WATER'),
            'Water',
            Money::fromDecimalString('0.60'),
            Quantity::of(10),
        );

        self::assertFalse(self::water(10)->equals($cheaper));
    }

    private static function water(int $units): Product
    {
        return new Product(
            ProductSelector::fromString('WATER'),
            'Water',
            Money::fromDecimalString('0.65'),
            Quantity::of($units),
        );
    }
}
