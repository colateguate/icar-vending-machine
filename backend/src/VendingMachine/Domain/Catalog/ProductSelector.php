<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Exception\InvalidProductSelector;

/**
 * The label on the button a customer presses: WATER, JUICE, SODA.
 *
 * A validated string rather than an enum, and the contrast with
 * CoinDenomination is deliberate. The coins a machine accepts are a physical
 * property of the hardware — a closed set, known when the code is compiled, so
 * an enum is exactly right there. The products it sells are *data*: a service
 * technician restocks the machine with whatever the business decided to sell
 * this month, and stocking SPARKLING_WATER must not require a deployment.
 *
 * The format is still constrained, because a selector is an identifier and not
 * free text: an initial uppercase letter, then uppercase letters, digits,
 * underscores or hyphens, up to 32 characters. Nothing is normalised — a
 * selector that differs in case is a different selector, and leniency, if it
 * is ever wanted, belongs at the edge rather than inside the value.
 */
final readonly class ProductSelector
{
    private const FORMAT = '/^[A-Z][A-Z0-9_-]{0,31}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::FORMAT, $value)) {
            throw InvalidProductSelector::malformed($value, self::FORMAT);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
