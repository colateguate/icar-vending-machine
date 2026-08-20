<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Command;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\SpyEventBus;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class ReturnCoinsHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    /**
     * Example 2 from the brief, one level up from the aggregate: the same
     * coins come back, and they are gone from the machine afterwards.
     */
    public function test_it_hands_back_the_coins_that_were_inserted(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(
            CoinDenomination::TEN_CENTS,
            CoinDenomination::TEN_CENTS,
        );
        $handler = self::handler($repository, new SpyEventBus());

        $returned = $handler(new ReturnCoinsCommand());

        self::assertTrue($returned->equals(CoinCollection::of(
            CoinDenomination::TEN_CENTS,
            CoinDenomination::TEN_CENTS,
        )));
    }

    public function test_the_machine_no_longer_holds_them(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(CoinDenomination::TEN_CENTS);
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new ReturnCoinsCommand());

        self::assertTrue(
            $repository->find(MachineId::fromString(self::MACHINE_ID))->insertedCoins()->isEmpty(),
        );
    }

    public function test_it_announces_the_refund(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachineWith(CoinDenomination::TEN_CENTS), $events);

        $handler(new ReturnCoinsCommand());

        self::assertTrue($events->hasPublished(CoinsRefunded::class));
    }

    public function test_returning_nothing_is_allowed_and_announces_nothing(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachineWith(), $events);

        $returned = $handler(new ReturnCoinsCommand());

        self::assertTrue($returned->isEmpty());
        self::assertSame([], $events->published(), 'no coins left the machine, so there is nothing to report');
    }

    private static function aRepositoryHoldingAMachineWith(CoinDenomination ...$coins): InMemoryVendingMachineRepository
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId(self::MACHINE_ID)
                ->withInsertedCoins(...$coins)
                ->build(),
        );

        return $repository;
    }

    private static function handler(
        InMemoryVendingMachineRepository $repository,
        SpyEventBus $events,
    ): ReturnCoinsHandler {
        return new ReturnCoinsHandler(new MachineLocator($repository, self::MACHINE_ID), $repository, $events);
    }
}
