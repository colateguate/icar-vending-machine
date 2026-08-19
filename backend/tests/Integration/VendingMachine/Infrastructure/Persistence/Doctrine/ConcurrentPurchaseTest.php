<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doctrine\DoctrineTestEnvironment;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Exception\ConcurrentMachineModification;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\DoctrineVendingMachineRepository;
use PHPUnit\Framework\TestCase;

/**
 * The last can, and two people pressing the button at the same instant.
 *
 * Every other test in this suite runs one thing at a time, so this is the only
 * one that can catch the failure that matters here: both readers see stock of
 * one, both believe the sale is theirs, and without a version column both
 * writes land — one silently overwriting the other, leaving a can dispensed
 * twice and the till short.
 *
 * Two EntityManagers over the same file are what makes it a race rather than a
 * story: each has its own identity map and its own unit of work, which is what
 * two requests are.
 */
final class ConcurrentPurchaseTest extends TestCase
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

    public function test_only_one_of_two_simultaneous_purchases_is_kept(): void
    {
        $this->givenTheLastCan();

        $first = new DoctrineVendingMachineRepository($this->database->entityManager());
        $second = new DoctrineVendingMachineRepository($this->database->anotherEntityManager());

        // Both read before either writes. This is the whole scenario.
        $asFirstSawIt = $first->find(MachineId::fromString('lobby-01'));
        $asSecondSawIt = $second->find(MachineId::fromString('lobby-01'));

        $asFirstSawIt->purchase(ProductSelector::fromString('SODA'), new OptimalChangeStrategy());
        $asSecondSawIt->purchase(ProductSelector::fromString('SODA'), new OptimalChangeStrategy());

        $first->save($asFirstSawIt);

        $this->expectException(ConcurrentMachineModification::class);
        $second->save($asSecondSawIt);
    }

    public function test_the_can_that_was_not_sold_is_still_on_the_shelf(): void
    {
        $this->givenTheLastCan();

        $first = new DoctrineVendingMachineRepository($this->database->entityManager());
        $second = new DoctrineVendingMachineRepository($this->database->anotherEntityManager());
        $asFirstSawIt = $first->find(MachineId::fromString('lobby-01'));
        $asSecondSawIt = $second->find(MachineId::fromString('lobby-01'));
        $asFirstSawIt->purchase(ProductSelector::fromString('SODA'), new OptimalChangeStrategy());
        $asSecondSawIt->purchase(ProductSelector::fromString('SODA'), new OptimalChangeStrategy());
        $first->save($asFirstSawIt);

        try {
            $second->save($asSecondSawIt);
        } catch (ConcurrentMachineModification) {
            // The point of the test is what the machine looks like afterwards.
        }

        $stored = (new DoctrineVendingMachineRepository($this->database->anotherEntityManager()))
            ->find(MachineId::fromString('lobby-01'));

        self::assertSame(
            0,
            $stored->inventory()->find(ProductSelector::fromString('SODA'))->available()->units(),
            'exactly one can left the machine, not two',
        );
        self::assertSame(2, $stored->version(), 'only one of the two writes was kept');
    }

    private function givenTheLastCan(): void
    {
        (new DoctrineVendingMachineRepository($this->database->entityManager()))->save(
            VendingMachineBuilder::aMachine()
                ->withId('lobby-01')
                ->withProduct('SODA', 'Soda', '1.50', 1)
                ->withChangeReserve([5 => 10, 10 => 10, 25 => 10])
                ->withInsertedCoins(CoinDenomination::ONE_UNIT, CoinDenomination::ONE_UNIT)
                ->build(),
        );
    }
}
