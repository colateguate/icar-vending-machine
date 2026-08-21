<?php

declare(strict_types=1);

namespace App\Tests\Support\Contract;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

/**
 * What every VendingMachineRepository must guarantee, regardless of what it
 * stores machines in. Each adapter extends this and supplies an instance, so a
 * new adapter cannot quietly honour less than the port promises.
 *
 * Writing it as a contract from the first adapter, rather than retrofitting it
 * when the second arrives, is what forces the question "what must ANY
 * implementation guarantee?" instead of "what does this one happen to do?".
 *
 * What deliberately does NOT live here: anything about object identity. The
 * in-memory adapter copies on read, so two reads hand back independent
 * objects; Doctrine keeps an identity map, so two reads inside one unit of
 * work hand back the very same object. Both are legitimate — the port promises
 * state, not instances — so those expectations belong to the adapter tests
 * that can actually keep them.
 */
abstract class VendingMachineRepositoryContract extends TestCase
{
    abstract protected function repository(): VendingMachineRepository;

    final public function test_it_returns_a_machine_it_was_given(): void
    {
        $repository = $this->repository();
        $machine = VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build();

        $repository->save($machine);

        self::assertMachineStateMatches($machine, $repository->find(MachineId::fromString('lobby-01')));
    }

    final public function test_it_fails_when_the_machine_was_never_provisioned(): void
    {
        $this->expectException(MachineNotFound::class);
        $this->expectExceptionMessage('lobby-01');

        $this->repository()->find(MachineId::fromString('lobby-01'));
    }

    final public function test_saving_again_keeps_the_latest_state(): void
    {
        $repository = $this->repository();
        $machine = VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build();
        $repository->save($machine);

        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $repository->save($machine);

        // The whole aggregate, not just the field that changed: an adapter
        // whose update path writes only the mutated column and wipes the rest
        // would satisfy a narrower assertion while destroying the machine.
        self::assertMachineStateMatches($machine, $repository->find(MachineId::fromString('lobby-01')));
    }

    /**
     * Two machines that differ only in which coins they take must come back
     * different. An adapter that stored nothing at all would pass every other
     * test here, because every other test builds a machine taking the same four
     * coins — so the narrowed set and the empty one are asked for by name.
     */
    final public function test_it_remembers_which_coins_a_machine_takes(): void
    {
        $repository = $this->repository();
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId('lobby-01')
                ->accepting(CoinDenomination::TEN_CENTS, CoinDenomination::TWO_UNITS)
                ->build(),
        );
        $repository->save(
            VendingMachineBuilder::aStockedMachine()->withId('lobby-02')->acceptingNothing()->build(),
        );

        $narrowed = $repository->find(MachineId::fromString('lobby-01'));
        self::assertTrue($narrowed->acceptedCoins()->accepts(CoinDenomination::TWO_UNITS));
        self::assertFalse($narrowed->acceptedCoins()->accepts(CoinDenomination::FIVE_CENTS));

        self::assertTrue(
            $repository->find(MachineId::fromString('lobby-02'))->isOutOfService(),
            'a machine switched off at the acceptor comes back switched off',
        );
    }

    final public function test_it_keeps_machines_apart_by_identifier(): void
    {
        $repository = $this->repository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build());
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId('lobby-02')
                ->withInsertedCoins(CoinDenomination::ONE_UNIT)
                ->build(),
        );

        self::assertSame('lobby-01', $repository->find(MachineId::fromString('lobby-01'))->id()->value());
        self::assertTrue($repository->find(MachineId::fromString('lobby-01'))->insertedAmount()->isZero());
        self::assertSame(
            1,
            $repository->find(MachineId::fromString('lobby-02'))->insertedCoins()->countOf(CoinDenomination::ONE_UNIT),
        );
    }

    /**
     * Compares what the port promises to preserve — state — rather than
     * instances.
     *
     * Asserts on plain projections instead of the value objects' equals(), so
     * a failure prints the actual difference. A mapping bug in a persistence
     * adapter is tedious enough to chase without "false is not true" being the
     * only clue.
     */
    final protected static function assertMachineStateMatches(VendingMachine $expected, VendingMachine $actual): void
    {
        self::assertSame($expected->id()->value(), $actual->id()->value(), 'identifier');
        self::assertSame(self::describe($expected->inventory()), self::describe($actual->inventory()), 'inventory');
        self::assertSame(
            $expected->changeReserve()->toArray(),
            $actual->changeReserve()->toArray(),
            'change reserve',
        );
        self::assertSame($expected->insertedCoins()->toArray(), $actual->insertedCoins()->toArray(), 'escrow');
        self::assertTrue(
            $expected->acceptedCoins()->equals($actual->acceptedCoins()),
            'which coins the machine takes',
        );
    }

    /**
     * @return array<string, array{name: string, price: string, available: int}>
     */
    private static function describe(Inventory $inventory): array
    {
        $described = [];
        foreach ($inventory->all() as $product) {
            $described[$product->selector()->value()] = [
                'name' => $product->name(),
                'price' => $product->price()->toDecimalString(),
                'available' => $product->available()->units(),
            ];
        }

        return $described;
    }
}
