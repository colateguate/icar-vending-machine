<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Shared;

use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;

/**
 * Answers "which machine are we talking about?" so that no handler has to.
 *
 * This deployment serves one physical machine, and its identifier is
 * configuration rather than something a caller supplies — which is why the API
 * route is /api/machine and not /api/machines/{id}. Keeping that assumption in
 * one class means serving a fleet later changes this file and the routes,
 * rather than all six use cases.
 */
final readonly class MachineLocator
{
    public function __construct(
        private VendingMachineRepository $repository,
        private string $machineId,
    ) {
    }

    /**
     * @throws MachineNotFound
     */
    public function locate(): VendingMachine
    {
        return $this->repository->find($this->machineId());
    }

    public function machineId(): MachineId
    {
        return MachineId::fromString($this->machineId);
    }

    /**
     * Only provisioning asks this. Every other use case wants the machine and
     * should fail if there is none — a handler that quietly did nothing
     * because the machine was missing would turn "not ready yet" into a silent
     * success.
     */
    public function isProvisioned(): bool
    {
        try {
            $this->locate();

            return true;
        } catch (MachineNotFound) {
            return false;
        }
    }
}
