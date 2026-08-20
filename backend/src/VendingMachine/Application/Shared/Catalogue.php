<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Shared;

use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Money\Money;

/**
 * Turns the plain rows a command carries into shelves the machine understands.
 *
 * Two use cases load a machine — a service visit and the first provisioning —
 * and they load it from the same shape. Doing the translation in each handler
 * would be the same twelve lines twice, and the day one of them learns to
 * accept a new field the other would keep rejecting it.
 *
 * Every conversion here is also a check: a price that is not an amount, a
 * selector in the wrong shape, a negative count — each is refused by the value
 * object that would have had to hold it. That is why there is no validation
 * step in front of this.
 */
final class Catalogue
{
    /**
     * @param list<array{selector: string, name: string, price: string, count: int}> $products
     */
    public static function fromRows(array $products): Inventory
    {
        return Inventory::of(...array_map(
            static fn (array $product): Product => new Product(
                ProductSelector::fromString($product['selector']),
                $product['name'],
                Money::fromDecimalString($product['price']),
                Quantity::of($product['count']),
            ),
            $products,
        ));
    }
}
