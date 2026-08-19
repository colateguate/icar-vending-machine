<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Catalog;

use InvalidArgumentException;

/**
 * A count of physical units sitting in a slot. Never negative.
 *
 * Reaching zero is normal and is not this type's problem to report: running
 * out of stock is a business outcome the catalog raises as ProductOutOfStock.
 * Going *below* zero is impossible in the real machine, so it can only mean a
 * missing check upstream — a bug, and it throws accordingly.
 */
final readonly class Quantity
{
    private function __construct(private int $units)
    {
        if ($units < 0) {
            throw new InvalidArgumentException(\sprintf('A quantity cannot be negative, got %d.', $units));
        }
    }

    public static function of(int $units): self
    {
        return new self($units);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function units(): int
    {
        return $this->units;
    }

    public function isZero(): bool
    {
        return 0 === $this->units;
    }

    public function decrement(): self
    {
        if ($this->isZero()) {
            throw new InvalidArgumentException('Cannot take a unit from a slot with no units left.');
        }

        return new self($this->units - 1);
    }

    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }
}
