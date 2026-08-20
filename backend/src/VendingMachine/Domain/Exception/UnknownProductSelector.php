<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class UnknownProductSelector extends DomainException implements VendingMachineError
{
    public static function notStocked(string $selector): self
    {
        return new self(\sprintf('This machine does not stock any product under the selector "%s".', $selector));
    }
}
