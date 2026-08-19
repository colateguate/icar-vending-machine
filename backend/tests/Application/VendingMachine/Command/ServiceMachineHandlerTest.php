<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Command;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\SpyEventBus;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class ServiceMachineHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_it_replaces_what_the_machine_stocks(): void
    {
        $repository = self::aRepositoryHoldingAMachine();
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new ServiceMachineCommand(
            [['selector' => 'WATER', 'name' => 'Still Water', 'price' => '0.70', 'count' => 4]],
            [25 => 6],
        ));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        $water = $stored->inventory()->find(ProductSelector::fromString('WATER'));
        self::assertSame('Still Water', $water->name());
        self::assertSame(4, $water->available()->units());
        self::assertTrue($water->price()->equals(Money::fromDecimalString('0.70')));
        self::assertFalse($stored->inventory()->has(ProductSelector::fromString('SODA')), 'SERVICE sets, it does not top up');
    }

    public function test_it_replaces_the_change_reserve(): void
    {
        $repository = self::aRepositoryHoldingAMachine();
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new ServiceMachineCommand([], [5 => 3]));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertSame(3, $stored->changeReserve()->countOf(CoinDenomination::FIVE_CENTS));
        self::assertSame(0, $stored->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    public function test_it_announces_the_visit(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachine(), $events);

        $handler(new ServiceMachineCommand([], []));

        self::assertTrue($events->hasPublished(MachineServiced::class));
    }

    public function test_it_gives_back_money_a_customer_had_inserted(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId(self::MACHINE_ID)
                ->withInsertedCoins(CoinDenomination::TWENTY_FIVE_CENTS)
                ->build(),
        );
        $events = new SpyEventBus();
        $handler = self::handler($repository, $events);

        $handler(new ServiceMachineCommand([], []));

        self::assertTrue($repository->find(MachineId::fromString(self::MACHINE_ID))->insertedCoins()->isEmpty());
        self::assertTrue($events->hasPublished(CoinsRefunded::class));
    }

    public function test_the_command_carries_plain_arrays(): void
    {
        $command = new ServiceMachineCommand(
            [['selector' => 'WATER', 'name' => 'Water', 'price' => '0.65', 'count' => 10]],
            [25 => 10],
        );

        self::assertSame('WATER', $command->products[0]['selector']);
        self::assertSame(10, $command->changeReserve[25]);
    }

    public function test_a_price_that_is_not_a_decimal_amount_is_refused(): void
    {
        $handler = self::handler(self::aRepositoryHoldingAMachine(), new SpyEventBus());

        $this->expectException(InvalidMoneyAmount::class);

        $handler(new ServiceMachineCommand(
            [['selector' => 'WATER', 'name' => 'Water', 'price' => 'free', 'count' => 1]],
            [],
        ));
    }

    public function test_a_coin_the_machine_does_not_take_is_refused(): void
    {
        $handler = self::handler(self::aRepositoryHoldingAMachine(), new SpyEventBus());

        $this->expectException(UnsupportedCoin::class);

        $handler(new ServiceMachineCommand([], [20 => 5]));
    }

    private static function aRepositoryHoldingAMachine(): InMemoryVendingMachineRepository
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());

        return $repository;
    }

    private static function handler(
        InMemoryVendingMachineRepository $repository,
        SpyEventBus $events,
    ): ServiceMachineHandler {
        return new ServiceMachineHandler(new MachineLocator($repository, self::MACHINE_ID), $repository, $events);
    }
}
