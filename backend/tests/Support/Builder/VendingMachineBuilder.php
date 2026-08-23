<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

/**
 * Makes the intent of a test's starting state visible and hides the setup
 * noise. Events recorded while building are drained before handing the machine
 * over: arranging a scenario is not the behaviour under test.
 */
final class VendingMachineBuilder
{
    private const DEFAULT_ID = 'test-machine';

    private string $id = self::DEFAULT_ID;

    /** @var list<Product> */
    private array $products = [];

    private CoinCollection $changeReserve;

    private AcceptedCoins $acceptedCoins;

    /** @var list<CoinDenomination> */
    private array $insertedCoins = [];

    private function __construct()
    {
        $this->changeReserve = CoinCollection::empty();
        // The four the brief names, so a test that says nothing about coins
        // gets the machine the brief describes.
        $this->acceptedCoins = AcceptedCoins::of(
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::TEN_CENTS,
            CoinDenomination::TWENTY_FIVE_CENTS,
            CoinDenomination::ONE_UNIT,
        );
    }

    public static function aMachine(): self
    {
        return new self();
    }

    /**
     * Water 0.65, Juice 1.00, Soda 1.50 — the catalogue from the brief.
     */
    public static function aStockedMachine(): self
    {
        return self::aMachine()
            ->withProduct('WATER', 'Water', '0.65', 10)
            ->withProduct('JUICE', 'Juice', '1.00', 10)
            ->withProduct('SODA', 'Soda', '1.50', 10)
            ->withChangeReserve([5 => 10, 10 => 10, 25 => 10]);
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withProduct(string $selector, string $name, string $price, int $units): self
    {
        $this->products[] = new Product(
            ProductSelector::fromString($selector),
            $name,
            Money::fromDecimalString($price),
            Quantity::of($units),
        );

        return $this;
    }

    /**
     * @param array<int, int> $counts denomination value in cents => how many
     */
    public function withChangeReserve(array $counts): self
    {
        $this->changeReserve = CoinCollection::fromCounts($counts);

        return $this;
    }

    public function withNoChange(): self
    {
        $this->changeReserve = CoinCollection::empty();

        return $this;
    }

    public function accepting(CoinDenomination ...$coins): self
    {
        $this->acceptedCoins = AcceptedCoins::of(...$coins);

        return $this;
    }

    /**
     * A machine switched off at the acceptor: it reads no coin, so nobody can
     * pay it.
     */
    public function acceptingNothing(): self
    {
        $this->acceptedCoins = AcceptedCoins::none();

        return $this;
    }

    public function withInsertedCoins(CoinDenomination ...$coins): self
    {
        $this->insertedCoins = array_values($coins);

        return $this;
    }

    public function build(): VendingMachine
    {
        $machine = VendingMachine::provision(
            MachineId::fromString($this->id),
            Inventory::of(...$this->products),
            $this->changeReserve,
            $this->acceptedCoins,
        );

        foreach ($this->insertedCoins as $coin) {
            $machine->insert($coin);
        }

        $machine->releaseEvents();

        return $machine;
    }
}
