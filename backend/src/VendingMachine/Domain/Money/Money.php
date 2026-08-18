<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Money;

use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use InvalidArgumentException;

/**
 * A monetary amount, held as an integer number of cents.
 *
 * Never a float: under IEEE-754 `0.1 + 0.2 !== 0.3`, which makes both equality
 * and accumulation unsound — and in a machine that hands back change, a
 * rounding error is missing money. An integer is exact, totally ordered,
 * hashable and free to serialize.
 *
 * A single implicit currency is assumed; the extension point is a Currency
 * value object held alongside the amount, deliberately not built yet.
 */
final readonly class Money
{
    private const CENTS_PER_UNIT = 100;

    private const DECIMAL_FORMAT = '/^\d+(\.\d{1,2})?$/';

    private function __construct(private int $amountInCents)
    {
        if ($amountInCents < 0) {
            throw new InvalidArgumentException(\sprintf('A monetary amount cannot be negative, got %d cents.', $amountInCents));
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses the wire format used across the API ("0.65"). Amounts travel as
     * decimal strings rather than JSON numbers so the client cannot inherit
     * the same floating-point trap we avoided here.
     */
    public static function fromDecimalString(string $amount): self
    {
        if (1 !== preg_match(self::DECIMAL_FORMAT, $amount)) {
            throw InvalidMoneyAmount::notADecimalAmount($amount);
        }

        [$units, $decimals] = explode('.', $amount) + [1 => ''];

        return new self((int) $units * self::CENTS_PER_UNIT + (int) str_pad($decimals, 2, '0'));
    }

    public function cents(): int
    {
        return $this->amountInCents;
    }

    public function add(self $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function subtract(self $other): self
    {
        return new self($this->amountInCents - $other->amountInCents);
    }

    public function multiplyBy(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(\sprintf('A monetary amount cannot be multiplied by a negative factor, got %d.', $factor));
        }

        return new self($this->amountInCents * $factor);
    }

    public function isZero(): bool
    {
        return 0 === $this->amountInCents;
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->amountInCents >= $other->amountInCents;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amountInCents < $other->amountInCents;
    }

    public function toDecimalString(): string
    {
        return \sprintf(
            '%d.%02d',
            intdiv($this->amountInCents, self::CENTS_PER_UNIT),
            $this->amountInCents % self::CENTS_PER_UNIT,
        );
    }
}
