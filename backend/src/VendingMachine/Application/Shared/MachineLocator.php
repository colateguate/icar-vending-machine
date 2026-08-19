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
 * rather than all five use cases.
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
        return $this->repository->find(MachineId::fromString($this->machineId));
    }
}
