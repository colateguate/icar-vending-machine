<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

/**
 * Nobody's fault but ours: the machine has not been provisioned yet. The edge
 * maps it to 503 rather than a 4xx, because the caller did nothing wrong.
 */
final class MachineNotFound extends DomainException implements VendingMachineError
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No machine has been provisioned under the id "%s".', $id));
    }
}
