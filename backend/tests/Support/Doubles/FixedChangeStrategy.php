<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;

/**
 * A stand-in policy for tests about the aggregate rather than the algorithm.
 * The only legitimate double at this level: everything else the aggregate
 * touches is a value object, and those are built real.
 *
 * It also records what it was asked for, so a test can assert which pool of
 * coins the aggregate offered it — that is how "the escrow joins the reserve
 * before change is chosen" gets verified from the outside.
 */
final class FixedChangeStrategy implements ChangeStrategy
{
    private ?Money $askedFor = null;

    private ?CoinCollection $offered = null;

    private function __construct(
        private readonly ?CoinCollection $change,
    ) {
    }

    public static function returning(CoinCollection $change): self
    {
        return new self($change);
    }

    public static function refusing(): self
    {
        return new self(null);
    }

    public function selectCoins(Money $amount, CoinCollection $available): CoinCollection
    {
        $this->askedFor = $amount;
        $this->offered = $available;

        return $this->change ?? throw CannotDispenseChange::forAmount($amount);
    }

    public function askedFor(): ?Money
    {
        return $this->askedFor;
    }

    public function offered(): ?CoinCollection
    {
        return $this->offered;
    }
}
