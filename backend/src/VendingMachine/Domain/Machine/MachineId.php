<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Machine;

use InvalidArgumentException;

/**
 * Identifies one physical machine.
 *
 * There is deliberately no generate(): minting an identifier is an
 * infrastructure decision, and randomness inside the domain would make every
 * test that builds a machine non-deterministic. Whoever provisions the machine
 * supplies the identifier.
 */
final readonly class MachineId
{
    private const MAX_LENGTH = 64;

    private function __construct(private string $value)
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException('A machine identifier cannot be blank.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('A machine identifier cannot exceed %d characters, got %d.', self::MAX_LENGTH, \strlen($value)));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
