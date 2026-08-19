<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use App\VendingMachine\Domain\Money\Money;
use DomainException;

/**
 * The money in the escrow does not cover the price. Carries the shortfall so
 * the customer is told how much more to insert rather than just "no".
 */
final class InsufficientFunds extends DomainException implements VendingMachineError
{
    private function __construct(private readonly Money $missingAmount, string $message)
    {
        parent::__construct($message);
    }

    public static function needsMore(Money $missingAmount): self
    {
        return new self($missingAmount, \sprintf(
            'Another %s is needed before this product can be dispensed.',
            $missingAmount->toDecimalString(),
        ));
    }

    public function missingAmount(): Money
    {
        return $this->missingAmount;
    }
}
