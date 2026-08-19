<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Contract\VendingMachineRepositoryContract;
use App\Tests\Support\Doctrine\DoctrineTestEnvironment;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\DoctrineVendingMachineRepository;

/**
 * The same contract the in-memory double signs, against a real database.
 *
 * This class holds almost nothing, and that is the point: everything worth
 * asserting about a repository was already written once, when the first
 * adapter arrived. If this file needed to relax an expectation to go green,
 * the port would be a suggestion rather than a contract.
 *
 * What does belong here are the guarantees only a real database can make: that
 * the state survives a round trip through columns, and that reading again in a
 * fresh unit of work still finds it.
 */
final class DoctrineVendingMachineRepositoryTest extends VendingMachineRepositoryContract
{
    private DoctrineTestEnvironment $database;

    protected function setUp(): void
    {
        $this->database = DoctrineTestEnvironment::boot();
    }

    protected function tearDown(): void
    {
        $this->database->shutdown();
    }

    protected function repository(): VendingMachineRepository
    {
        return new DoctrineVendingMachineRepository($this->database->entityManager());
    }

    /**
     * The contract is satisfied by an adapter that never touches the disk, so
     * this asks the only question it cannot: is the machine still there when
     * nothing is holding it in memory any more?
     */
    public function test_the_machine_survives_a_new_unit_of_work(): void
    {
        $this->repository()->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId('lobby-01')
                ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS)
                ->build(),
        );

        $elsewhere = new DoctrineVendingMachineRepository($this->database->anotherEntityManager());
        $machine = $elsewhere->find(MachineId::fromString('lobby-01'));

        self::assertCount(3, $machine->inventory()->all());
        self::assertSame('0.25', $machine->insertedAmount()->toDecimalString());
        self::assertSame(10, $machine->changeReserve()->countOf(CoinDenomination::FIVE_CENTS));
    }

    /**
     * The price is 1.05 on purpose: a hundred and five cents, so a value that
     * survived as a float or lost its trailing digit would not come back
     * looking plausible. The other fields are values nothing else in the suite
     * uses, so a mapping that returned some other product's row would show.
     */
    public function test_the_catalogue_comes_back_with_every_field_intact(): void
    {
        $this->repository()->save(
            VendingMachineBuilder::aMachine()
                ->withId('lobby-01')
                ->withProduct('SPARKLING_WATER', 'Sparkling Water', '1.05', 7)
                ->build(),
        );

        $product = (new DoctrineVendingMachineRepository($this->database->anotherEntityManager()))
            ->find(MachineId::fromString('lobby-01'))
            ->inventory()
            ->all()[0];

        self::assertSame('SPARKLING_WATER', $product->selector()->value());
        self::assertSame('Sparkling Water', $product->name());
        self::assertSame('1.05', $product->price()->toDecimalString());
        self::assertSame(7, $product->available()->units());
    }

    /**
     * Doctrine keeps an identity map, so a second read inside the same unit of
     * work hands back the very object the first one did. That is correct
     * behaviour and the opposite of what the in-memory double does, which is
     * exactly why neither expectation belongs in the shared contract.
     */
    public function test_two_reads_in_one_unit_of_work_are_the_same_object(): void
    {
        $repository = $this->repository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build());

        self::assertSame(
            $repository->find(MachineId::fromString('lobby-01')),
            $repository->find(MachineId::fromString('lobby-01')),
        );
    }

    public function test_a_new_machine_starts_at_version_one(): void
    {
        $repository = $this->repository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build());

        self::assertSame(1, $repository->find(MachineId::fromString('lobby-01'))->version());
    }

    public function test_the_version_moves_on_every_write(): void
    {
        $repository = $this->repository();
        $machine = VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build();
        $repository->save($machine);

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $repository->save($machine);

        self::assertSame(
            2,
            (new DoctrineVendingMachineRepository($this->database->anotherEntityManager()))
                ->find(MachineId::fromString('lobby-01'))
                ->version(),
        );
    }
}
