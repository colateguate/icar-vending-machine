<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Machine;

use InvalidArgumentException;
use Stringable;

/**
 * Identifies one physical machine.
 *
 * There is deliberately no generate(): minting an identifier is an
 * infrastructure decision, and randomness inside the domain would make every
 * test that builds a machine non-deterministic. Whoever provisions the machine
 * supplies the identifier.
 */
final readonly class MachineId implements Stringable
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

    /**
     * An identifier that can spell itself. Nothing in the model needs this —
     * value() is what the model calls — but anything that keys a map by an
     * identity does, and that includes the persistence adapter's identity map.
     *
     * Stated as an interface rather than left as a bare magic method so it
     * reads as part of what a MachineId is, and so a reader is not left
     * wondering who calls it.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
