<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Command;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\FailingOnSaveRepository;
use App\Tests\Support\Doubles\SpyEventBus;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Event\CoinInserted;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\Money;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Use-case level: no kernel, no database. What is under test is the
 * orchestration — load, act, save, publish — not the business rules, which the
 * unit suite already pins down on the aggregate itself.
 */
final class InsertCoinHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_it_puts_the_coin_in_the_machine(): void
    {
        $repository = self::aRepositoryHoldingAMachine();
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new InsertCoinCommand(25));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertTrue($stored->insertedAmount()->equals(Money::fromDecimalString('0.25')));
    }

    public function test_it_saves_the_machine_so_the_coin_survives_the_request(): void
    {
        $repository = self::aRepositoryHoldingAMachine();
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new InsertCoinCommand(25));
        $handler(new InsertCoinCommand(10));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertTrue(
            $stored->insertedAmount()->equals(Money::fromDecimalString('0.35')),
            'the second coin must land on a machine that already had the first',
        );
    }

    public function test_it_announces_the_coin_after_saving(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachine(), $events);

        $handler(new InsertCoinCommand(5));

        self::assertTrue($events->hasPublished(CoinInserted::class));
        self::assertCount(1, $events->published());
    }

    /**
     * The announcement has to come after the write, and the only way to show
     * that is to make the write fail: asserting which events were published
     * cannot distinguish the two orderings, because the same event comes out
     * either way. A sale nobody recorded must not be announced.
     */
    public function test_nothing_is_announced_when_the_write_fails(): void
    {
        $events = new SpyEventBus();
        $repository = self::aRepositoryHoldingAMachine();
        $handler = new InsertCoinHandler(
            new MachineLocator($repository, self::MACHINE_ID),
            new FailingOnSaveRepository($repository),
            $events,
        );

        try {
            $handler(new InsertCoinCommand(25));
            self::fail('Expected the write to fail.');
        } catch (RuntimeException) {
            // expected: the database is unreachable
        }

        self::assertSame([], $events->published());
    }

    public function test_the_command_carries_a_plain_number(): void
    {
        $command = new InsertCoinCommand(25);

        self::assertSame(25, $command->coinInCents, 'a message must survive being serialised');
    }

    public function test_a_coin_the_machine_does_not_take_is_refused(): void
    {
        $handler = self::handler(self::aRepositoryHoldingAMachine(), new SpyEventBus());

        $this->expectException(UnsupportedCoin::class);

        $handler(new InsertCoinCommand(20));
    }

    public function test_nothing_is_announced_when_the_coin_is_refused(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachine(), $events);

        try {
            $handler(new InsertCoinCommand(20));
        } catch (UnsupportedCoin) {
            // expected
        }

        self::assertSame([], $events->published());
    }

    public function test_it_fails_when_no_machine_has_been_provisioned(): void
    {
        $handler = self::handler(new InMemoryVendingMachineRepository(), new SpyEventBus());

        $this->expectException(MachineNotFound::class);

        $handler(new InsertCoinCommand(25));
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
    ): InsertCoinHandler {
        return new InsertCoinHandler(
            new MachineLocator($repository, self::MACHINE_ID),
            $repository,
            $events,
        );
    }
}
