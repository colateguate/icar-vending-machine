<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Dispensing;

use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;

/**
 * What came out of the machine: the item, and the coins that fell into the tray
 * with it.
 *
 * It copies the fields it needs out of the Product rather than holding on to
 * it. Keeping the reference would mean carrying a stock count taken before the
 * sale was applied — a number that is neither the shelf as it was nor as it is
 * now. Suppressing the accessor would hide that, not fix it, and the next
 * person to add a getter or an equals() would inherit the wrong figure with no
 * warning. This value object stores exactly what it exposes; the current stock
 * is what the machine state query is for.
 */
final readonly class DispensedGoods
{
    private function __construct(
        private ProductSelector $selector,
        private string $name,
        private Money $price,
        private CoinCollection $change,
    ) {
    }

    public static function of(Product $product, CoinCollection $change): self
    {
        return new self($product->selector(), $product->name(), $product->price(), $change);
    }

    public function selector(): ProductSelector
    {
        return $this->selector;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function change(): CoinCollection
    {
        return $this->change;
    }

    public function changeAmount(): Money
    {
        return $this->change->total();
    }
}
