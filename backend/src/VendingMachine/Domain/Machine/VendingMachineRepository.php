<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Machine;

use App\VendingMachine\Domain\Exception\MachineNotFound;

/**
 * The port through which the domain reaches persistence.
 *
 * Declared here, in the domain, because the domain is the consumer — that is
 * what makes the dependency point inwards. Implementations live in
 * Infrastructure and are bound to this interface in the container.
 *
 * find() returns a machine or throws; it never returns null. A caller holding
 * a nullable aggregate is a caller that will forget to check, and "this
 * machine was never provisioned" is a named situation rather than an absence.
 */
interface VendingMachineRepository
{
    /**
     * @throws MachineNotFound
     */
    public function find(MachineId $id): VendingMachine;

    public function save(VendingMachine $machine): void;
}
