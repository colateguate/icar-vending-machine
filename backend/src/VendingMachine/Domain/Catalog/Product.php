<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Money\Money;

/**
 * One slot of the machine: what it sells, at what price, and how much is left.
 *
 * Identified by its selector — that is what the Inventory keys it by — but
 * modelled immutably: dispensing returns a new Product rather than mutating
 * this one. The aggregate root is the only thing in this domain that changes
 * state; everything below it is a value that gets replaced.
 *
 * The name is a plain string on purpose. It is display text with no business
 * rule attached, and wrapping it in a value object would be inventing a
 * requirement that does not exist.
 */
final readonly class Product
{
    public function __construct(
        private ProductSelector $selector,
        private string $name,
        private Money $price,
        private Quantity $available,
    ) {
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

    public function available(): Quantity
    {
        return $this->available;
    }

    public function isOutOfStock(): bool
    {
        return $this->available->isZero();
    }

    public function dispenseOne(): self
    {
        if ($this->isOutOfStock()) {
            throw ProductOutOfStock::forSelector($this->selector->value());
        }

        return new self($this->selector, $this->name, $this->price, $this->available->decrement());
    }

    public function equals(self $other): bool
    {
        return $this->selector->equals($other->selector)
            && $this->name === $other->name
            && $this->price->equals($other->price)
            && $this->available->equals($other->available);
    }
}
