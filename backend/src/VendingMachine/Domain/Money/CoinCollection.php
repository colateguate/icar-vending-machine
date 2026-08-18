<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Money;

use InvalidArgumentException;

/**
 * An immutable multiset of coins.
 *
 * One type serves both roles the machine needs — the escrow of coins a
 * customer has inserted, and the reserve it can pay change from. They are the
 * same concept (a bag of coins) and are told apart by the field that holds
 * them, not by a wrapper type per role.
 *
 * Kept canonical at all times: denominations with a zero count are dropped and
 * the map is ordered by denomination, so structural comparison is enough to
 * decide equality.
 */
final readonly class CoinCollection
{
    /**
     * @param array<int, int> $countsByDenomination canonical: ascending keys, no zero counts
     */
    private function __construct(private array $countsByDenomination)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(CoinDenomination ...$denominations): self
    {
        $counts = [];
        foreach ($denominations as $denomination) {
            $counts[$denomination->value] = ($counts[$denomination->value] ?? 0) + 1;
        }

        return self::canonical($counts);
    }

    /**
     * @param array<int, int> $countsByDenomination denomination value in cents => how many
     */
    public static function fromCounts(array $countsByDenomination): self
    {
        return self::canonical($countsByDenomination);
    }

    public function add(CoinDenomination $denomination): self
    {
        $counts = $this->countsByDenomination;
        $counts[$denomination->value] = ($counts[$denomination->value] ?? 0) + 1;

        return self::canonical($counts);
    }

    public function merge(self $other): self
    {
        $counts = $this->countsByDenomination;
        foreach ($other->countsByDenomination as $value => $count) {
            $counts[$value] = ($counts[$value] ?? 0) + $count;
        }

        return self::canonical($counts);
    }

    public function subtract(self $other): self
    {
        $counts = $this->countsByDenomination;
        foreach ($other->countsByDenomination as $value => $count) {
            $available = $counts[$value] ?? 0;
            if ($available < $count) {
                throw new InvalidArgumentException(\sprintf('Cannot take %d coin(s) of %d cents from a collection holding %d.', $count, $value, $available));
            }
            $counts[$value] = $available - $count;
        }

        return self::canonical($counts);
    }

    public function countOf(CoinDenomination $denomination): int
    {
        return $this->countsByDenomination[$denomination->value] ?? 0;
    }

    public function total(): Money
    {
        $total = Money::zero();
        foreach ($this->countsByDenomination as $value => $count) {
            $total = $total->add(Money::fromCents($value)->multiplyBy($count));
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return [] === $this->countsByDenomination;
    }

    /**
     * The subset the machine is allowed to hand back as change.
     */
    public function dispensableOnly(): self
    {
        $counts = array_filter(
            $this->countsByDenomination,
            static fn (int $value): bool => CoinDenomination::fromCents($value)->isDispensableAsChange(),
            \ARRAY_FILTER_USE_KEY,
        );

        return self::canonical($counts);
    }

    public function equals(self $other): bool
    {
        return $this->countsByDenomination === $other->countsByDenomination;
    }

    /**
     * @return array<int, int> denomination value in cents => how many
     */
    public function toArray(): array
    {
        return $this->countsByDenomination;
    }

    /**
     * @param array<int, int> $counts
     */
    private static function canonical(array $counts): self
    {
        $canonical = [];
        foreach ($counts as $value => $count) {
            $denomination = CoinDenomination::fromCents($value);

            if ($count < 0) {
                throw new InvalidArgumentException(\sprintf('A coin count cannot be negative, got %d for %d cents.', $count, $value));
            }

            if ($count > 0) {
                $canonical[$denomination->value] = $count;
            }
        }

        ksort($canonical);

        return new self($canonical);
    }
}
