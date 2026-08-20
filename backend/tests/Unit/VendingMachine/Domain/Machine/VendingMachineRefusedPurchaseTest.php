<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\FixedChangeStrategy;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Every way a purchase can be turned down, and the promise that comes with all
 * of them: a refused sale changes nothing. The aggregate resolves the product,
 * checks the stock, checks the money and composes the change before it touches
 * a single field, so there is no half-finished sale to unwind — and no can that
 * has already dropped to un-drop.
 */
final class VendingMachineRefusedPurchaseTest extends TestCase
{
    public function test_it_refuses_a_product_it_does_not_stock(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $this->expectException(UnknownProductSelector::class);
        $this->expectExceptionMessage('BEER');

        $machine->purchase(self::selector('BEER'), self::optimal());
    }

    public function test_it_refuses_a_product_that_is_sold_out(): void
    {
        $machine = VendingMachineBuilder::aMachine()
            ->withProduct('WATER', 'Water', '0.65', 0)
            ->withChangeReserve([25 => 10, 10 => 10, 5 => 10])
            ->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $this->expectException(ProductOutOfStock::class);
        $this->expectExceptionMessage('WATER');

        $machine->purchase(self::selector('WATER'), self::optimal());
    }

    public function test_it_refuses_when_not_enough_money_has_been_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        $this->expectException(InsufficientFunds::class);

        $machine->purchase(self::selector('WATER'), self::optimal());
    }

    public function test_it_refuses_when_nothing_has_been_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $this->expectException(InsufficientFunds::class);

        $machine->purchase(self::selector('WATER'), self::optimal());
    }

    public function test_the_refusal_says_how_much_more_is_needed(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        try {
            $machine->purchase(self::selector('WATER'), self::optimal());
            self::fail('Expected the machine to refuse.');
        } catch (InsufficientFunds $refusal) {
            self::assertTrue(
                $refusal->missingAmount()->equals(Money::fromDecimalString('0.40')),
                '0.65 costs 0.40 more than the 0.25 inserted',
            );
            self::assertStringContainsString('0.40', $refusal->getMessage());
        }
    }

    public function test_paying_one_cent_short_is_still_short(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TEN_CENTS);

        $this->expectException(InsufficientFunds::class);

        // 0.60 against a 0.65 price.
        $machine->purchase(self::selector('WATER'), self::optimal());
    }

    public function test_it_refuses_a_sale_it_cannot_give_change_for(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $this->expectException(CannotDispenseChange::class);
        $this->expectExceptionMessage('0.35');

        $machine->purchase(self::selector('WATER'), self::optimal());
    }

    /**
     * The decision from ADR-0007: the customer keeps their money in escrow so
     * they can take it back or pick something cheaper. Auto-refunding would
     * throw away the intent they already expressed.
     */
    public function test_a_sale_refused_for_change_leaves_the_money_where_it_was(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        try {
            $machine->purchase(self::selector('WATER'), self::optimal());
        } catch (CannotDispenseChange) {
            // expected
        }

        self::assertSame(1, $machine->insertedCoins()->countOf(CoinDenomination::ONE_UNIT));
        self::assertTrue($machine->insertedAmount()->equals(Money::fromDecimalString('1.00')));
    }

    public function test_the_customer_can_still_take_their_money_back_afterwards(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->withNoChange()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        try {
            $machine->purchase(self::selector('WATER'), self::optimal());
        } catch (CannotDispenseChange) {
            // expected
        }

        self::assertTrue(
            $machine->returnInsertedCoins()->equals(CoinCollection::of(CoinDenomination::ONE_UNIT)),
            'RETURN-COIN stays the one way money leaves the machine',
        );
    }

    /**
     * @return iterable<string, array{callable(VendingMachine): void}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown product' => [static function (VendingMachine $machine): void {
            $machine->purchase(ProductSelector::fromString('BEER'), new OptimalChangeStrategy());
        }];

        yield 'sold out' => [static function (VendingMachine $machine): void {
            $machine->purchase(ProductSelector::fromString('EMPTY'), new OptimalChangeStrategy());
        }];

        yield 'not enough money' => [static function (VendingMachine $machine): void {
            $machine->purchase(ProductSelector::fromString('SODA'), new OptimalChangeStrategy());
        }];

        yield 'change cannot be composed' => [static function (VendingMachine $machine): void {
            $machine->purchase(ProductSelector::fromString('WATER'), FixedChangeStrategy::refusing());
        }];
    }

    /**
     * Compute-then-commit, checked for every refusal there is: inventory,
     * reserve and escrow must come out byte for byte as they went in.
     *
     * @param callable(VendingMachine): void $refusedPurchase
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function test_a_refused_purchase_leaves_the_machine_exactly_as_it_was(callable $refusedPurchase): void
    {
        $machine = self::aMachineWithSomethingToLose();
        $inventoryBefore = $machine->inventory();
        $reserveBefore = $machine->changeReserve();
        $escrowBefore = $machine->insertedCoins();
        $machine->releaseEvents();

        try {
            $refusedPurchase($machine);
            self::fail('Expected the machine to refuse.');
        } catch (VendingMachineError) {
            // expected: every refusal is a named business outcome
        }

        self::assertTrue($inventoryBefore->equals($machine->inventory()), 'inventory moved');
        self::assertTrue($reserveBefore->equals($machine->changeReserve()), 'change reserve moved');
        self::assertTrue($escrowBefore->equals($machine->insertedCoins()), 'escrow moved');
        self::assertSame([], $machine->releaseEvents(), 'a sale that did not happen must not be announced');
    }

    /**
     * Error precedence, and the only way to prove the aggregate's own stock
     * guard is doing the work. Dispensing checks the stock again on its way
     * out, so removing the guard would still end in ProductOutOfStock and no
     * snapshot assertion could tell the two apart. What does tell them apart is
     * whether the change policy was ever consulted: there is nothing to sell,
     * so there is nothing to work out change for.
     */
    public function test_a_sold_out_product_is_refused_before_the_change_policy_is_consulted(): void
    {
        $machine = self::aMachineWithSomethingToLose();
        $policy = FixedChangeStrategy::returning(CoinCollection::fromCounts([25 => 1, 10 => 1]));

        try {
            $machine->purchase(self::selector('EMPTY'), $policy);
            self::fail('Expected the machine to refuse.');
        } catch (ProductOutOfStock) {
            // expected
        }

        self::assertNull($policy->askedFor(), 'the policy was asked to price change for a sale that cannot happen');
    }

    /**
     * The guarantee has to hold even when the failure is our own bug rather
     * than a business outcome. A policy that breaks its contract and hands back
     * coins the machine does not hold makes the reserve arithmetic throw a plain
     * SPL exception — and that must still find the machine untouched, because
     * the alternative is a product gone with the till unchanged.
     *
     * This is why both new states are computed before either is assigned: the
     * subset check inside subtract() is the last thing that can fail, so it has
     * to fail before the stock moves.
     */
    public function test_a_policy_that_breaks_its_contract_still_leaves_the_machine_untouched(): void
    {
        $machine = self::aMachineWithSomethingToLose();
        $inventoryBefore = $machine->inventory();
        $reserveBefore = $machine->changeReserve();
        $escrowBefore = $machine->insertedCoins();

        // The machine holds one nickel; this policy claims to hand back twenty.
        $rogue = FixedChangeStrategy::returning(CoinCollection::fromCounts([5 => 20]));

        try {
            $machine->purchase(self::selector('WATER'), $rogue);
            self::fail('Expected the reserve arithmetic to reject coins the machine does not hold.');
        } catch (InvalidArgumentException $bug) {
            self::assertNotInstanceOf(
                VendingMachineError::class,
                $bug,
                'a policy breaking its contract is our defect, not a business outcome',
            );
        }

        self::assertTrue($inventoryBefore->equals($machine->inventory()), 'stock moved on a failed sale');
        self::assertTrue($reserveBefore->equals($machine->changeReserve()), 'change reserve moved');
        self::assertTrue($escrowBefore->equals($machine->insertedCoins()), 'escrow moved');
    }

    /**
     * A machine with stock, a till and money in the escrow — so that a refusal
     * has something it could plausibly have damaged.
     *
     * The reserve is not arbitrary: it must be able to serve WATER's change
     * (0.35 from 1.00) so that in the sold-out case the stock guard is the only
     * thing standing between the customer and a sale. Shrink it and the refusal
     * would come from CannotDispenseChange instead, quietly testing a different
     * path than the case name claims.
     */
    private static function aMachineWithSomethingToLose(): VendingMachine
    {
        $machine = VendingMachineBuilder::aMachine()
            ->withProduct('WATER', 'Water', '0.65', 4)
            ->withProduct('SODA', 'Soda', '1.50', 2)
            ->withProduct('EMPTY', 'Sold Out', '0.65', 0)
            ->withChangeReserve([25 => 3, 10 => 2, 5 => 1])
            ->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        return $machine;
    }

    private static function optimal(): ChangeStrategy
    {
        return new OptimalChangeStrategy();
    }

    private static function selector(string $value): ProductSelector
    {
        return ProductSelector::fromString($value);
    }
}
