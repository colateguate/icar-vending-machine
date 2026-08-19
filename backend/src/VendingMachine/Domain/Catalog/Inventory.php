<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use InvalidArgumentException;

/**
 * Everything the machine currently sells, keyed by selector.
 *
 * Immutable like the rest of the domain: dispensing returns a new Inventory.
 * Kept ordered by selector so listings are stable, which keeps the API
 * response predictable and makes structural equality meaningful.
 */
final readonly class Inventory
{
    /**
     * @param array<string, Product> $productsBySelector ordered by selector
     */
    private function __construct(private array $productsBySelector)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(Product ...$products): self
    {
        $bySelector = [];
        foreach ($products as $product) {
            $selector = $product->selector()->value();
            if (isset($bySelector[$selector])) {
                throw new InvalidArgumentException(\sprintf('A machine cannot stock two products under the selector "%s".', $selector));
            }
            $bySelector[$selector] = $product;
        }

        ksort($bySelector);

        return new self($bySelector);
    }

    public function has(ProductSelector $selector): bool
    {
        return isset($this->productsBySelector[$selector->value()]);
    }

    public function find(ProductSelector $selector): Product
    {
        return $this->productsBySelector[$selector->value()]
            ?? throw UnknownProductSelector::notStocked($selector->value());
    }

    /**
     * @throws UnknownProductSelector
     * @throws ProductOutOfStock
     */
    public function dispense(ProductSelector $selector): self
    {
        $remaining = $this->productsBySelector;
        $remaining[$selector->value()] = $this->find($selector)->dispenseOne();

        return new self($remaining);
    }

    /**
     * @return list<Product>
     */
    public function all(): array
    {
        return array_values($this->productsBySelector);
    }

    public function isEmpty(): bool
    {
        return [] === $this->productsBySelector;
    }

    public function equals(self $other): bool
    {
        if (array_keys($this->productsBySelector) !== array_keys($other->productsBySelector)) {
            return false;
        }

        foreach ($this->productsBySelector as $selector => $product) {
            if (!$product->equals($other->productsBySelector[$selector])) {
                return false;
            }
        }

        return true;
    }
}
