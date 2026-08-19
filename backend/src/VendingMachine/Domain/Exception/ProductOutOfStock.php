<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class ProductOutOfStock extends DomainException implements VendingMachineError
{
    public static function forSelector(string $selector): self
    {
        return new self(\sprintf('The product "%s" is sold out.', $selector));
    }
}
