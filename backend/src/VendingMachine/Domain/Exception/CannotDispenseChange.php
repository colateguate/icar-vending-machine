<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use App\VendingMachine\Domain\Money\Money;
use DomainException;

/**
 * The machine owes change it cannot physically compose from the coins it
 * holds. A business outcome, not a bug: the sale is refused and the customer's
 * coins stay in escrow so they can take them back or pick something cheaper.
 *
 * Carries the amount owed, which the edge surfaces as changeDue so the
 * customer is told what the machine could not pay rather than just "no".
 */
final class CannotDispenseChange extends DomainException implements VendingMachineError
{
    private function __construct(private readonly Money $amountDue, string $message)
    {
        parent::__construct($message);
    }

    public static function forAmount(Money $amountDue): self
    {
        return new self($amountDue, \sprintf(
            'The machine cannot compose %s in change from the coins it holds.',
            $amountDue->toDecimalString(),
        ));
    }

    public function amountDue(): Money
    {
        return $this->amountDue;
    }
}
