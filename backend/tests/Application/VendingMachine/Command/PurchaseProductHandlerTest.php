<?php

declare(strict_types=1);

namespace App\Tests\Application\VendingMachine\Command;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\SpyEventBus;
use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductCommand;
use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductHandler;
use App\VendingMachine\Application\Shared\MachineLocator;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Event\ProductDispensed;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InvalidProductSelector;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use App\VendingMachine\Infrastructure\Persistence\InMemory\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class PurchaseProductHandlerTest extends TestCase
{
    private const MACHINE_ID = 'lobby-01';

    public function test_it_hands_back_what_the_machine_dispensed(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(CoinDenomination::ONE_UNIT);
        $handler = self::handler($repository, new SpyEventBus());

        $dispensed = $handler(new PurchaseProductCommand('WATER'));

        self::assertSame('WATER', $dispensed->selector()->value());
        self::assertTrue($dispensed->change()->equals(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        )));
    }

    /**
     * The change physically left the machine, so no later question could
     * recover it. That is the whole reason this command answers at all.
     */
    public function test_the_change_cannot_be_recovered_from_the_machine_afterwards(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(CoinDenomination::ONE_UNIT);
        $handler = self::handler($repository, new SpyEventBus());

        $dispensed = $handler(new PurchaseProductCommand('WATER'));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertTrue($stored->insertedCoins()->isEmpty(), 'the escrow is gone');
        self::assertFalse(
            $stored->changeReserve()->equals($dispensed->change()),
            'and the reserve is not the change either',
        );
    }

    public function test_it_saves_the_sale(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(CoinDenomination::ONE_UNIT);
        $handler = self::handler($repository, new SpyEventBus());

        $handler(new PurchaseProductCommand('WATER'));

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertSame(9, $stored->inventory()->find(ProductSelector::fromString('WATER'))->available()->units());
    }

    public function test_it_announces_the_sale(): void
    {
        $events = new SpyEventBus();
        $handler = self::handler(self::aRepositoryHoldingAMachineWith(CoinDenomination::ONE_UNIT), $events);

        $handler(new PurchaseProductCommand('WATER'));

        self::assertTrue($events->hasPublished(ProductDispensed::class));
    }

    public function test_the_command_carries_a_plain_string(): void
    {
        self::assertSame('SODA', (new PurchaseProductCommand('SODA'))->selector);
    }

    public function test_a_malformed_selector_is_refused_before_anything_happens(): void
    {
        $repository = self::aRepositoryHoldingAMachineWith(CoinDenomination::ONE_UNIT);
        $events = new SpyEventBus();
        $handler = self::handler($repository, $events);

        try {
            $handler(new PurchaseProductCommand('water'));
            self::fail('Expected the selector to be rejected.');
        } catch (InvalidProductSelector) {
            // expected: the value object is what validates, not the handler
        }

        self::assertSame([], $events->published());
        self::assertTrue(
            $repository->find(MachineId::fromString(self::MACHINE_ID))->insertedAmount()
                ->equals(Money::fromDecimalString('1.00')),
            'the money is untouched',
        );
    }

    public function test_a_refused_sale_is_not_saved_and_not_announced(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $repository->save(
            VendingMachineBuilder::aStockedMachine()
                ->withId(self::MACHINE_ID)
                ->withNoChange()
                ->withInsertedCoins(CoinDenomination::ONE_UNIT)
                ->build(),
        );
        $events = new SpyEventBus();
        $handler = self::handler($repository, $events);

        try {
            $handler(new PurchaseProductCommand('WATER'));
            self::fail('Expected the machine to refuse for want of change.');
        } catch (CannotDispenseChange) {
            // expected
        }

        $stored = $repository->find(MachineId::fromString(self::MACHINE_ID));
        self::assertSame(10, $stored->inventory()->find(ProductSelector::fromString('WATER'))->available()->units());
        self::assertSame([], $events->published());
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
    ): PurchaseProductHandler {
        return new PurchaseProductHandler(
            new MachineLocator($repository, self::MACHINE_ID),
            $repository,
            $events,
            new OptimalChangeStrategy(),
        );
    }
}
