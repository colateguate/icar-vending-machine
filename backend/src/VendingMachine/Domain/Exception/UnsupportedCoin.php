<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class UnsupportedCoin extends DomainException implements VendingMachineError
{
    public static function ofCents(int $cents): self
    {
        return new self(\sprintf('The machine does not accept coins of %d cents.', $cents));
    }
}
