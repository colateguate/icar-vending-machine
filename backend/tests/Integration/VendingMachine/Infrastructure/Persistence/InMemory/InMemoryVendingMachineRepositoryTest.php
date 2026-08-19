<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\InMemory;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Contract\VendingMachineRepositoryContract;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;

/**
 * The shared contract, plus the one guarantee that is specific to this
 * adapter: it must not be more forgiving than a real database. A test double
 * that lets an unsaved change leak into storage would make the application
 * suite pass on code that breaks in production, which is worse than having no
 * double at all.
 *
 * These copy-on-read expectations are deliberately not part of the contract:
 * Doctrine's identity map returns the same instance twice within a unit of
 * work, and that is correct behaviour for it.
 */
final class InMemoryVendingMachineRepositoryTest extends VendingMachineRepositoryContract
{
    protected function repository(): VendingMachineRepository
    {
        return new InMemoryVendingMachineRepository();
    }

    public function test_a_stored_machine_ignores_later_changes_to_the_caller_copy(): void
    {
        $repository = $this->repository();
        $machine = VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build();
        $repository->save($machine);

        $machine->insert(CoinDenomination::ONE_UNIT);

        self::assertTrue(
            $repository->find(MachineId::fromString('lobby-01'))->insertedAmount()->isZero(),
            'a real database would not see an unsaved change; the double must not either',
        );
    }

    public function test_each_read_hands_out_an_independent_copy(): void
    {
        $repository = $this->repository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build());

        $first = $repository->find(MachineId::fromString('lobby-01'));
        $first->insert(CoinDenomination::ONE_UNIT);

        self::assertTrue($repository->find(MachineId::fromString('lobby-01'))->insertedAmount()->isZero());
    }
}
