<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\InMemory;

use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;

/**
 * Keeps machines in an array. Used by the application-level tests, where a
 * real database would add setup cost without adding an answer.
 *
 * It copies on the way in and on the way out, so a caller that mutates an
 * aggregate without saving does not silently change what is stored — which is
 * how a real database behaves, and a test double that lies about that is worse
 * than no double at all.
 *
 * A shallow clone is enough here, and that is a property of the model rather
 * than luck: every field of the aggregate is an immutable value object, and
 * its behaviour replaces those fields instead of mutating them. Nothing
 * reachable from a copy can be changed through the original.
 */
final class InMemoryVendingMachineRepository implements VendingMachineRepository
{
    /** @var array<string, VendingMachine> */
    private array $machines = [];

    public function find(MachineId $id): VendingMachine
    {
        $machine = $this->machines[$id->value()] ?? throw MachineNotFound::withId($id->value());

        return clone $machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->machines[$machine->id()->value()] = clone $machine;
    }
}
