<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function test_an_empty_inventory_stocks_nothing(): void
    {
        $inventory = Inventory::empty();

        self::assertTrue($inventory->isEmpty());
        self::assertSame([], $inventory->all());
        self::assertFalse($inventory->has(self::selector('WATER')));
    }

    public function test_it_stocks_the_products_it_was_provisioned_with(): void
    {
        $inventory = self::threeProducts();

        self::assertFalse($inventory->isEmpty());
        self::assertTrue($inventory->has(self::selector('WATER')));
        self::assertTrue($inventory->has(self::selector('JUICE')));
        self::assertTrue($inventory->has(self::selector('SODA')));
        self::assertFalse($inventory->has(self::selector('BEER')));
    }

    public function test_it_refuses_two_products_sharing_a_selector(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WATER');

        Inventory::of(self::product('WATER', '0.65', 10), self::product('WATER', '0.70', 5));
    }

    public function test_it_finds_a_product_by_selector(): void
    {
        $water = self::threeProducts()->find(self::selector('WATER'));

        self::assertSame('Water', $water->name());
        self::assertTrue($water->price()->equals(Money::fromDecimalString('0.65')));
    }

    public function test_finding_a_product_the_machine_does_not_stock_fails(): void
    {
        $this->expectException(UnknownProductSelector::class);
        $this->expectExceptionMessage('BEER');

        self::threeProducts()->find(self::selector('BEER'));
    }

    public function test_an_unknown_selector_is_a_business_error(): void
    {
        $this->expectException(VendingMachineError::class);

        self::threeProducts()->find(self::selector('BEER'));
    }

    public function test_dispensing_reduces_only_the_selected_product(): void
    {
        $afterSale = self::threeProducts()->dispense(self::selector('SODA'));

        self::assertSame(4, $afterSale->find(self::selector('SODA'))->available()->units());
        self::assertSame(10, $afterSale->find(self::selector('WATER'))->available()->units());
        self::assertSame(7, $afterSale->find(self::selector('JUICE'))->available()->units());
    }

    public function test_dispensing_leaves_the_original_inventory_untouched(): void
    {
        $inventory = self::threeProducts();

        $inventory->dispense(self::selector('SODA'));

        self::assertSame(5, $inventory->find(self::selector('SODA'))->available()->units());
    }

    public function test_dispensing_a_product_the_machine_does_not_stock_fails(): void
    {
        $this->expectException(UnknownProductSelector::class);
        $this->expectExceptionMessage('BEER');

        self::threeProducts()->dispense(self::selector('BEER'));
    }

    public function test_dispensing_a_sold_out_product_fails(): void
    {
        $soldOut = Inventory::of(self::product('WATER', '0.65', 0));

        $this->expectException(ProductOutOfStock::class);
        $this->expectExceptionMessage('WATER');

        $soldOut->dispense(self::selector('WATER'));
    }

    public function test_it_lists_its_products_ordered_by_selector(): void
    {
        $selectors = array_map(
            static fn (Product $product): string => $product->selector()->value(),
            self::threeProducts()->all(),
        );

        self::assertSame(['JUICE', 'SODA', 'WATER'], $selectors, 'a stable order keeps the API response predictable');
    }

    public function test_inventories_holding_the_same_products_are_equal(): void
    {
        $one = Inventory::of(self::product('WATER', '0.65', 10), self::product('SODA', '1.50', 5));
        $other = Inventory::of(self::product('SODA', '1.50', 5), self::product('WATER', '0.65', 10));

        self::assertTrue($one->equals($other), 'provisioning order must not matter');
    }

    public function test_inventories_differing_in_stock_are_not_equal(): void
    {
        $one = Inventory::of(self::product('WATER', '0.65', 10));
        $other = Inventory::of(self::product('WATER', '0.65', 9));

        self::assertFalse($one->equals($other));
    }

    public function test_an_empty_inventory_only_equals_another_empty_one(): void
    {
        self::assertTrue(Inventory::empty()->equals(Inventory::empty()));
        self::assertFalse(Inventory::empty()->equals(self::threeProducts()));
    }

    private static function threeProducts(): Inventory
    {
        return Inventory::of(
            self::product('WATER', '0.65', 10),
            self::product('JUICE', '1.00', 7),
            self::product('SODA', '1.50', 5),
        );
    }

    private static function product(string $selector, string $price, int $units): Product
    {
        return new Product(
            self::selector($selector),
            ucfirst(strtolower($selector)),
            Money::fromDecimalString($price),
            Quantity::of($units),
        );
    }

    private static function selector(string $value): ProductSelector
    {
        return ProductSelector::fromString($value);
    }
}
