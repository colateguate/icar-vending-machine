<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class InvalidMoneyAmount extends DomainException implements VendingMachineError
{
    public static function notADecimalAmount(string $amount): self
    {
        return new self(\sprintf(
            'Expected a positive decimal amount with at most two decimals, got "%s".',
            $amount,
        ));
    }
}
