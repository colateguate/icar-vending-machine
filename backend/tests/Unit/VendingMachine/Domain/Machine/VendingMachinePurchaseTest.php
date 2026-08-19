<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\FixedChangeStrategy;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\OptimalChangeStrategy;
use App\VendingMachine\Domain\Event\ProductDispensed;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class VendingMachinePurchaseTest extends TestCase
{
    /**
     * Example 1 from the brief, as executable specification:
     *
     *   1, 0.25, 0.25, GET-SODA  ->  SODA
     */
    public function test_example_1_buying_a_soda_with_exact_change(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);

        $dispensed = $machine->purchase(self::selector('SODA'), self::optimal());

        self::assertSame('SODA', $dispensed->selector()->value());
        self::assertSame('Soda', $dispensed->name());
        self::assertTrue($dispensed->change()->isEmpty(), 'paying exactly leaves nothing to give back');
        self::assertTrue($dispensed->changeAmount()->isZero());
    }

    /**
     * Example 3 from the brief:
     *
     *   1, GET-WATER  ->  WATER, 0.25, 0.10
     */
    public function test_example_3_buying_water_with_a_single_unit_coin(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $dispensed = $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertSame('WATER', $dispensed->selector()->value());
        self::assertTrue(
            $dispensed->change()->equals(CoinCollection::of(
                CoinDenomination::TWENTY_FIVE_CENTS,
                CoinDenomination::TEN_CENTS,
            )),
            'the brief spells out the coins: 0.25 and 0.10',
        );
    }

    public function test_the_change_it_hands_back_leaves_the_reserve(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertSame(9, $machine->changeReserve()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertSame(9, $machine->changeReserve()->countOf(CoinDenomination::TEN_CENTS));
        self::assertSame(10, $machine->changeReserve()->countOf(CoinDenomination::FIVE_CENTS));
    }

    public function test_the_coins_the_customer_paid_with_stay_in_the_machine(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertSame(
            1,
            $machine->changeReserve()->countOf(CoinDenomination::ONE_UNIT),
            'the coin dropped into the machine and belongs to it now',
        );
    }

    /**
     * The business invariant that ties it all together: whatever coins moved in
     * and out, the machine ends up richer by exactly the price of the item.
     */
    public function test_the_till_grows_by_exactly_the_price(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $before = $machine->changeReserve()->total();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $dispensed = $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertTrue(
            $machine->changeReserve()->total()->equals($before->add($dispensed->price())),
            'coins in minus change out must equal the price',
        );
    }

    public function test_a_purchase_empties_the_escrow(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertTrue($machine->insertedCoins()->isEmpty());
        self::assertTrue($machine->insertedAmount()->isZero());
    }

    public function test_only_the_product_bought_leaves_the_shelf(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertSame(9, $machine->inventory()->find(self::selector('WATER'))->available()->units());
        self::assertSame(10, $machine->inventory()->find(self::selector('JUICE'))->available()->units());
        self::assertSame(10, $machine->inventory()->find(self::selector('SODA'))->available()->units());
    }

    public function test_paying_the_exact_price_is_enough(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);

        $dispensed = $machine->purchase(self::selector('JUICE'), self::optimal());

        self::assertSame('JUICE', $dispensed->selector()->value());
        self::assertTrue($dispensed->change()->isEmpty());
    }

    public function test_it_records_what_was_dispensed(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);
        $machine->releaseEvents();

        $machine->purchase(self::selector('WATER'), self::optimal());

        $changeGivenBack = CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        );

        $events = $machine->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductDispensed::class, $events[0]);
        self::assertSame('WATER', $events[0]->selector()->value());
        self::assertTrue($events[0]->price()->equals(Money::fromDecimalString('0.65')));
        self::assertTrue($events[0]->change()->equals($changeGivenBack));
        self::assertTrue($events[0]->machineId()->equals($machine->id()));
    }

    /**
     * Double dispatch: the policy arrives as an argument, so the aggregate can
     * be exercised without the real algorithm deciding the outcome.
     */
    public function test_it_dispenses_the_change_the_policy_chose(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);
        $policy = FixedChangeStrategy::returning(CoinCollection::of(
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        ));

        $dispensed = $machine->purchase(self::selector('WATER'), $policy);

        self::assertSame(5, $dispensed->change()->countOf(CoinDenomination::FIVE_CENTS));
        self::assertTrue($dispensed->changeAmount()->equals(Money::fromDecimalString('0.35')));
    }

    public function test_it_asks_the_policy_for_the_overpaid_amount(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();
        $machine->insert(CoinDenomination::ONE_UNIT);
        $policy = FixedChangeStrategy::returning(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        ));

        $machine->purchase(self::selector('WATER'), $policy);

        self::assertTrue(
            $policy->askedFor()?->equals(Money::fromDecimalString('0.35')) ?? false,
            'the change owed is what was inserted minus the price',
        );
    }

    /**
     * The coins a customer just inserted are physically inside the machine, so
     * they are available to pay change with. The aggregate offers the whole
     * pool and lets the policy do the filtering, keeping that rule in one place.
     */
    public function test_it_offers_the_policy_the_escrow_along_with_the_reserve(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withChangeReserve([25 => 1, 10 => 1])
            ->build();
        $machine->insert(CoinDenomination::ONE_UNIT);
        $policy = FixedChangeStrategy::returning(CoinCollection::of(
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
        ));

        $machine->purchase(self::selector('WATER'), $policy);

        $offered = $policy->offered();
        self::assertNotNull($offered);
        self::assertSame(1, $offered->countOf(CoinDenomination::TWENTY_FIVE_CENTS), 'from the reserve');
        self::assertSame(1, $offered->countOf(CoinDenomination::TEN_CENTS), 'from the reserve');
        self::assertSame(
            1,
            $offered->countOf(CoinDenomination::ONE_UNIT),
            'and the escrow, handed over unfiltered so the policy owns that rule',
        );
    }

    public function test_change_can_be_paid_out_of_what_the_customer_just_inserted(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()
            ->withNoChange()
            ->build();
        // 0.70 for a 0.65 item, and the only nickel in the machine is one the
        // customer just put in.
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TWENTY_FIVE_CENTS);
        $machine->insert(CoinDenomination::TEN_CENTS);
        $machine->insert(CoinDenomination::FIVE_CENTS);
        $machine->insert(CoinDenomination::FIVE_CENTS);

        $dispensed = $machine->purchase(self::selector('WATER'), self::optimal());

        self::assertTrue(
            $dispensed->change()->equals(CoinCollection::of(CoinDenomination::FIVE_CENTS)),
            'an empty reserve can still pay 0.05 back out of the coins just inserted',
        );
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
