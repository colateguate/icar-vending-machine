<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Money;

/**
 * Which coins this machine's slot takes.
 *
 * The counterpart to CoinDenomination, and the reason that one is not enough:
 * reading a coin is a property of the hardware and never changes, while taking
 * it is a decision a technician makes and unmakes. One is a type, the other is
 * state — a set of denominations that a service visit replaces outright.
 *
 * A set rather than a CoinCollection: this says *which* coins, never how many.
 *
 * It may be empty, and that is the point rather than an oversight. A machine
 * that takes no coin cannot be paid, so it cannot sell anything: "out of
 * service" needs no flag of its own because the model already says it.
 */
final readonly class AcceptedCoins
{
    /**
     * @param list<CoinDenomination> $denominations canonical: declaration order, no repeats
     */
    private function __construct(private array $denominations)
    {
    }

    public static function of(CoinDenomination ...$denominations): self
    {
        // Filtering the enum keeps the result in declaration order — smallest
        // first — whatever order the caller named them in, so two sets built
        // from the same coins are the same value.
        return new self(array_values(array_filter(
            CoinDenomination::cases(),
            static fn (CoinDenomination $candidate): bool => \in_array($candidate, $denominations, true),
        )));
    }

    /**
     * A machine switched off at the acceptor.
     */
    public static function none(): self
    {
        return new self([]);
    }

    public function accepts(CoinDenomination $denomination): bool
    {
        return \in_array($denomination, $this->denominations, true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->denominations;
    }

    /**
     * @return list<CoinDenomination> smallest first
     */
    public function all(): array
    {
        return $this->denominations;
    }

    public function equals(self $other): bool
    {
        return $this->denominations === $other->denominations;
    }
}
