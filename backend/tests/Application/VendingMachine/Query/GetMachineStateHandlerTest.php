<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Query;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Application\Query\GetMachineState\GetMachineStateHandler;
use App\VendingMachine\Application\Query\GetMachineState\GetMachineStateQuery;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class GetMachineStateHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_it_reports_what_the_machine_sells(): void
    {
        $view = self::handler(self::aRepositoryHoldingAMachine())(new GetMachineStateQuery());

        $selectors = array_map(
            static fn ($product): string => $product->selector()->value(),
            $view->products,
        );
        self::assertSame(['JUICE', 'SODA', 'WATER'], $selectors);
    }

    public function test_it_reports_the_change_it_can_pay_with(): void
    {
        $view = self::handler(self::aRepositoryHoldingAMachine())(new GetMachineStateQuery());

        self::assertSame(10, $view->changeReserve->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    public function test_it_reports_the_money_currently_inserted(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId(self::MACHINE_ID)
                ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS, CoinDenomination::TEN_CENTS)
                ->build(),
        );

        $view = self::handler($repository)(new GetMachineStateQuery());

        self::assertTrue($view->insertedAmount->equals(Money::fromDecimalString('0.35')));
        self::assertSame(1, $view->insertedCoins->countOf(CoinDenomination::TEN_CENTS));
    }

    /**
     * The four are written out here rather than derived from CoinDenomination.
     * A test that builds its expectation from the same enum the code reads
     * asserts that the enum equals itself; this states what the machine takes.
     */
    public function test_it_reports_which_coins_it_takes(): void
    {
        $view = self::handler(self::aRepositoryHoldingAMachine())(new GetMachineStateQuery());

        self::assertSame(
            [
                CoinDenomination::FIVE_CENTS,
                CoinDenomination::TEN_CENTS,
                CoinDenomination::TWENTY_FIVE_CENTS,
                CoinDenomination::ONE_UNIT,
            ],
            $view->acceptedCoins,
        );
    }

    /**
     * What the machine takes does not depend on what it currently holds. A
     * machine with an empty till still accepts the same four coins, and a client
     * that dimmed a button because the reserve ran out would be refusing money
     * the machine is happy to take.
     */
    public function test_the_coins_it_takes_do_not_depend_on_the_coins_it_has(): void
    {
        $empty = new InMemoryVendingMachineRepository();
        $empty->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->withNoChange()->build());

        $view = self::handler($empty)(new GetMachineStateQuery());

        self::assertCount(4, $view->acceptedCoins);
    }

    public function test_it_reports_whether_exact_change_is_required(): void
    {
        $stocked = self::handler(self::aRepositoryHoldingAMachine())(new GetMachineStateQuery());
        self::assertFalse($stocked->exactChangeOnly);

        $empty = new InMemoryVendingMachineRepository();
        $empty->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->withNoChange()->build());

        self::assertTrue(self::handler($empty)(new GetMachineStateQuery())->exactChangeOnly);
    }

    public function test_asking_changes_nothing(): void
    {
        $repository = self::aRepositoryHoldingAMachine();
        $before = $repository->find(MachineId::fromString(self::MACHINE_ID));

        self::handler($repository)(new GetMachineStateQuery());

        $after = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertTrue($before->inventory()->equals($after->inventory()));
        self::assertTrue($before->changeReserve()->equals($after->changeReserve()));
        self::assertTrue($before->insertedCoins()->equals($after->insertedCoins()));
    }

    private static function aRepositoryHoldingAMachine(): InMemoryVendingMachineRepository
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());

        return $repository;
    }

    private static function handler(InMemoryVendingMachineRepository $repository): GetMachineStateHandler
    {
        return new GetMachineStateHandler(new MachineLocator($repository, self::MACHINE_ID));
    }
}
