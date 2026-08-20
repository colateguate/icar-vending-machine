<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\VendingMachine\Domain\Exception\ConcurrentMachineModification;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;

/**
 * Hands out the machine it holds, and loses every race to write it back.
 *
 * A stand-in for the moment optimistic locking fires: this caller read the
 * machine, someone else moved it, and the write now describes a version that
 * is no longer there. How a real adapter notices — a Doctrine version column
 * today — is deliberately not modelled here. The port names the failure, and
 * the name is the whole of what the layers above can see.
 *
 * It serves the machine itself instead of reading through a real repository,
 * and that is a consequence rather than a shortcut: replacing this service in
 * the test container only works while nothing has asked for it yet, so a
 * double that needed the real one to read through would need exactly the thing
 * whose absence makes the swap possible.
 */
final class LosingTheRaceRepository implements VendingMachineRepository
{
    public function __construct(private readonly VendingMachine $machine)
    {
    }

    public function find(MachineId $id): VendingMachine
    {
        return $id->equals($this->machine->id())
            ? $this->machine
            : throw MachineNotFound::withId($id->value());
    }

    public function save(VendingMachine $machine): void
    {
        throw ConcurrentMachineModification::of($machine->id()->value());
    }
}
