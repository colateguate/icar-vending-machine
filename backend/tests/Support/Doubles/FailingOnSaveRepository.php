<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use RuntimeException;

/**
 * Reads fine, refuses to write — a stand-in for the database being down.
 *
 * It exists to pin the ordering inside every command handler. Publishing an
 * event before the write succeeds would announce something that never
 * happened, and no assertion about *which* events were published can tell the
 * two orderings apart. Making the write fail can: if the announcement still
 * goes out, the handler published too early.
 */
final class FailingOnSaveRepository implements VendingMachineRepository
{
    public function __construct(private readonly VendingMachineRepository $reads)
    {
    }

    public function find(MachineId $id): VendingMachine
    {
        return $this->reads->find($id);
    }

    public function save(VendingMachine $machine): void
    {
        throw new RuntimeException('the database is unreachable');
    }
}
