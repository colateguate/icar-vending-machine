<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine;

use App\VendingMachine\Domain\Exception\ConcurrentMachineModification;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

/**
 * The driven side of the hexagon: the same port the in-memory double
 * implements, backed by a real database.
 *
 * It is this short because the mapping does the work. There is no assembling
 * of an aggregate out of rows and no translating of value objects — the XML
 * says which column is which, the custom types turn columns into value
 * objects, and what is left here is the two questions the port actually asks.
 *
 * Both of its methods exist to turn something Doctrine says into something the
 * domain named: a missing row into MachineNotFound, a stale write into
 * ConcurrentMachineModification. Everything above this class can then be
 * written as if databases did not exist, which is the entire point of putting
 * the interface in the domain.
 */
final readonly class DoctrineVendingMachineRepository implements VendingMachineRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(MachineId $id): VendingMachine
    {
        return $this->entityManager->find(VendingMachine::class, $id)
            ?? throw MachineNotFound::withId($id->value());
    }

    /**
     * Writes whatever the unit of work is holding, which is the aggregate the
     * caller has been changing: Doctrine tracked it from the moment find()
     * handed it over, so there is nothing to copy back.
     *
     * persist() is only meaningful for a machine that has never been stored;
     * for one that came out of find() it does nothing at all. Calling it
     * unconditionally keeps the port's single save() honest — the caller
     * should not have to know which of the two cases it is in.
     */
    public function save(VendingMachine $machine): void
    {
        $this->entityManager->persist($machine);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException $conflict) {
            throw ConcurrentMachineModification::of($machine->id()->value(), $conflict);
        }
    }
}
