<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bus;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Application\Query\GetMachineState\GetMachineStateQuery;
use App\VendingMachine\Application\Query\GetMachineState\MachineStateView;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Does the container actually connect the messages to their handlers?
 *
 * The unit and application suites build handlers by hand, so they pass whether
 * or not a single line of the container configuration is right. This one asks
 * the composed application, and it exists because the wiring genuinely broke
 * once: handler tagging was declared in an imported file, where Symfony's
 * _instanceof does not reach, and every test stayed green while nothing was
 * routed at all.
 */
final class BusWiringTest extends KernelTestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_inserting_a_coin_reaches_its_handler(): void
    {
        $this->givenAProvisionedMachine();

        $this->commandBus()->dispatch(new InsertCoinCommand(25));

        self::assertTrue($this->state()->insertedAmount->equals(Money::fromDecimalString('0.25')));
    }

    /**
     * Example 1 from the brief, dispatched the way the API will dispatch it.
     */
    public function test_example_1_buying_a_soda_with_exact_change(): void
    {
        $this->givenAProvisionedMachine();
        $commands = $this->commandBus();

        $commands->dispatch(new InsertCoinCommand(100));
        $commands->dispatch(new InsertCoinCommand(25));
        $commands->dispatch(new InsertCoinCommand(25));
        $dispensed = $commands->dispatch(new PurchaseProductCommand('SODA'));

        self::assertSame('SODA', $dispensed->selector()->value());
        self::assertTrue($dispensed->change()->isEmpty());
    }

    /**
     * Example 2: the coins come back, and the command bus carries them back
     * with them.
     */
    public function test_example_2_returning_the_coins_that_were_inserted(): void
    {
        $this->givenAProvisionedMachine();
        $commands = $this->commandBus();
        $commands->dispatch(new InsertCoinCommand(10));
        $commands->dispatch(new InsertCoinCommand(10));

        $returned = $commands->dispatch(new ReturnCoinsCommand());

        self::assertTrue($returned->equals(CoinCollection::of(
            CoinDenomination::TEN_CENTS,
            CoinDenomination::TEN_CENTS,
        )));
    }

    /**
     * Example 3: the change comes back as the coins the brief names.
     */
    public function test_example_3_buying_water_with_a_single_unit_coin(): void
    {
        $this->givenAProvisionedMachine();
        $commands = $this->commandBus();
        $commands->dispatch(new InsertCoinCommand(100));

        $dispensed = $commands->dispatch(new PurchaseProductCommand('WATER'));

        self::assertSame('WATER', $dispensed->selector()->value());
        self::assertTrue($dispensed->change()->equals(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        )));
    }

    public function test_servicing_the_machine_reaches_its_handler(): void
    {
        $this->givenAProvisionedMachine();

        $this->commandBus()->dispatch(new ServiceMachineCommand(
            [['selector' => 'WATER', 'name' => 'Water', 'price' => '0.65', 'count' => 2]],
            [5 => 4],
        ));

        $state = $this->state();
        self::assertCount(1, $state->products);
        self::assertSame(4, $state->changeReserve->countOf(CoinDenomination::FIVE_CENTS));
    }

    public function test_the_query_bus_answers_with_the_machine_state(): void
    {
        $this->givenAProvisionedMachine();

        $state = $this->state();

        self::assertCount(3, $state->products);
        self::assertFalse($state->exactChangeOnly);
        self::assertTrue($state->insertedAmount->isZero());
    }

    /**
     * The two buses are genuinely separate. Tagging every handler onto one bus
     * would work just as well from the outside, which is exactly why the
     * separation has to be asserted rather than assumed: a command must find
     * nobody listening on the query side.
     */
    public function test_a_command_has_no_handler_on_the_query_bus(): void
    {
        // No machine needed: the dispatch fails before any handler could look
        // for one.
        self::bootKernel();

        $rawQueryBus = self::getContainer()->get('test.raw_query_bus');
        self::assertInstanceOf(MessageBusInterface::class, $rawQueryBus);

        $this->expectException(NoHandlerForMessageException::class);

        $rawQueryBus->dispatch(new InsertCoinCommand(25));
    }

    private function givenAProvisionedMachine(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(VendingMachineRepository::class);
        self::assertInstanceOf(VendingMachineRepository::class, $repository);

        $repository->save(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());
    }

    private function commandBus(): CommandBus
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        return $bus;
    }

    private function state(): MachineStateView
    {
        $bus = self::getContainer()->get(QueryBus::class);
        self::assertInstanceOf(QueryBus::class, $bus);

        return $bus->ask(new GetMachineStateQuery());
    }
}
