<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Money\Money;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\Type\InventoryType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue is stored as one JSON document, so this type is the only thing
 * standing between the shelves and a column. Everything the domain knows about
 * a product has to survive it — and prices most of all, because a price that
 * comes back wrong is money.
 */
final class InventoryTypeTest extends TestCase
{
    public function test_a_catalogue_survives_the_round_trip(): void
    {
        $inventory = Inventory::of(
            self::aProduct('WATER', 'Water', '0.65', 10),
            self::aProduct('SODA', 'Soda', '1.50', 3),
        );

        self::assertTrue($inventory->equals(self::roundTrip($inventory)));
    }

    public function test_an_empty_catalogue_survives_the_round_trip(): void
    {
        self::assertTrue(self::roundTrip(Inventory::empty())->isEmpty());
    }

    /**
     * Prices are written as the integer number of cents the domain counts in,
     * not as a decimal string and never as a JSON number. A JSON number is a
     * float on the way back, which is the one thing the money model exists to
     * keep out.
     */
    public function test_a_price_is_stored_as_whole_cents(): void
    {
        $stored = (new InventoryType())->convertToDatabaseValue(
            Inventory::of(self::aProduct('WATER', 'Water', '0.65', 10)),
            new SQLitePlatform(),
        );

        self::assertSame(
            '[{"selector":"WATER","name":"Water","priceInCents":65,"available":10}]',
            $stored,
        );
    }

    public function test_a_price_comes_back_to_the_cent(): void
    {
        $restored = self::roundTrip(Inventory::of(self::aProduct('JUICE', 'Juice', '1.05', 1)));

        self::assertSame(105, $restored->find(ProductSelector::fromString('JUICE'))->price()->cents());
    }

    public function test_a_sold_out_slot_is_still_a_slot(): void
    {
        $restored = self::roundTrip(Inventory::of(self::aProduct('SODA', 'Soda', '1.50', 0)));

        self::assertTrue($restored->has(ProductSelector::fromString('SODA')));
        self::assertTrue($restored->find(ProductSelector::fromString('SODA'))->isOutOfStock());
    }

    public function test_a_missing_column_is_an_empty_catalogue(): void
    {
        self::assertTrue((new InventoryType())->convertToPHPValue(null, new SQLitePlatform())->isEmpty());
    }

    private static function roundTrip(Inventory $inventory): Inventory
    {
        $type = new InventoryType();
        $platform = new SQLitePlatform();

        return $type->convertToPHPValue($type->convertToDatabaseValue($inventory, $platform), $platform);
    }

    private static function aProduct(string $selector, string $name, string $price, int $units): Product
    {
        return new Product(
            ProductSelector::fromString($selector),
            $name,
            Money::fromDecimalString($price),
            Quantity::of($units),
        );
    }
}
