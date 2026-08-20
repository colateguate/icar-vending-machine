<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class InvalidProductSelector extends DomainException implements VendingMachineError
{
    public static function malformed(string $value, string $expectedFormat): self
    {
        return new self(\sprintf(
            'A product selector must match %s, got "%s".',
            $expectedFormat,
            $value,
        ));
    }
}
