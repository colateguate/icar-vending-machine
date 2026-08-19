<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Machine;

use App\Shared\Domain\AggregateRoot;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Dispensing\ChangeStrategy;
use App\VendingMachine\Domain\Dispensing\DispensedGoods;
use App\VendingMachine\Domain\Event\CoinInserted;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Event\MachineServiced;
use App\VendingMachine\Domain\Event\ProductDispensed;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

/**
 * The aggregate root, and the only thing in this domain that changes state.
 *
 * It is a single aggregate because a purchase enforces one invariant that
 * spans stock, the coin reserve and the escrow at the same instant: a product
 * leaves the slot if and only if its price is covered and the exact change can
 * be composed from coins physically inside the machine. Splitting inventory
 * and cash into separate aggregates would make that invariant eventually
 * consistent and would need a compensating action to un-vend a can that has
 * already dropped — which is not a thing. So they share one transactional
 * boundary.
 *
 * It does not grow without bound, because it scales by instance rather than by
 * size: one aggregate per physical machine, with MachineId as the natural
 * partition key. Fleet-level concerns (telemetry, restocking schedules, cash
 * reconciliation) belong to a separate context that consumes the events this
 * one records.
 *
 * Two collections of coins live here and they are not interchangeable. The
 * escrow still belongs to the customer until a sale completes; the reserve is
 * the machine's float for paying change.
 */
final class VendingMachine extends AggregateRoot
{
    /**
     * The one field here that is not about vending anything.
     *
     * Two customers can press the same button at the same instant, and the
     * cheapest honest answer is a counter the database checks on every write:
     * the second writer finds the number moved and is told so instead of
     * silently overwriting the first (ADR-0011). The domain never touches it —
     * the adapter that does the writing owns it entirely — but Doctrine can
     * only guard a field it can map, and the alternative was a second copy of
     * this aggregate living in the infrastructure and free to drift from it.
     */
    private int $version = 1;

    private function __construct(
        private readonly MachineId $id,
        private Inventory $inventory,
        private CoinCollection $changeReserve,
        private CoinCollection $insertedCoins,
    ) {
    }

    public static function provision(MachineId $id, Inventory $inventory, CoinCollection $changeReserve): self
    {
        return new self($id, $inventory, $changeReserve, CoinCollection::empty());
    }

    public function insert(CoinDenomination $coin): void
    {
        $this->insertedCoins = $this->insertedCoins->add($coin);

        $this->recordThat(new CoinInserted($this->id, $coin));
    }

    /**
     * Hands back the very coins that were inserted, not an equivalent amount:
     * the customer's coins are still sitting in the escrow, untouched.
     */
    public function returnInsertedCoins(): CoinCollection
    {
        $refunded = $this->insertedCoins;
        $this->insertedCoins = CoinCollection::empty();

        if (!$refunded->isEmpty()) {
            $this->recordThat(new CoinsRefunded($this->id, $refunded));
        }

        return $refunded;
    }

    /**
     * The sale, and the only place where the machine's three moving parts have
     * to agree at once.
     *
     * Everything is resolved and computed before anything is written: the
     * product, the stock, the money, the change, and both of the new states
     * that result. That ordering is not stylistic — a purchase that fails
     * halfway would leave a can gone with no change paid, and there is no
     * compensating action for a can that has already dropped. Every refusal
     * therefore escapes before the first field moves, which is what lets the
     * tests assert the machine is untouched.
     *
     * The stock is checked explicitly here even though dispensing would catch
     * it again further down. That is about which refusal the customer hears:
     * being told an item is sold out is more use than being told to insert more
     * money for something they could not have bought anyway. A test asserts the
     * change policy is never even consulted for a sale that cannot happen.
     *
     * The change comes out of the escrow *and* the reserve together, because
     * the coins the customer just inserted are physically inside the machine.
     * The pool is handed to the policy unfiltered: the port promises never to
     * return a coin the machine cannot dispense, so that rule lives with the
     * policy rather than being re-applied by every caller.
     *
     * The policy arrives as an argument rather than a constructor dependency —
     * double dispatch. An aggregate has to be reconstructible from persistence
     * without wiring services into it, and passing the policy per call means a
     * test can swap it without touching the machine.
     *
     * @throws UnknownProductSelector
     * @throws ProductOutOfStock
     * @throws InsufficientFunds
     * @throws CannotDispenseChange
     */
    public function purchase(ProductSelector $selector, ChangeStrategy $strategy): DispensedGoods
    {
        $product = $this->inventory->find($selector);

        if ($product->isOutOfStock()) {
            throw ProductOutOfStock::forSelector($selector->value());
        }

        $insertedAmount = $this->insertedAmount();
        $price = $product->price();

        if (!$insertedAmount->isGreaterThanOrEqualTo($price)) {
            throw InsufficientFunds::needsMore($price->subtract($insertedAmount));
        }

        $availableCoins = $this->changeReserve->merge($this->insertedCoins);
        $change = $strategy->selectCoins($insertedAmount->subtract($price), $availableCoins);

        // Both new states are computed while the machine is still untouched.
        // subtract() is the only check that the policy honoured its contract and
        // handed back coins this machine actually holds, so it has to run here:
        // doing it after the stock had already been decremented would leave a
        // product gone and the till unchanged.
        $remainingReserve = $availableCoins->subtract($change);
        $remainingStock = $this->inventory->dispense($selector);

        // Commit. Nothing below this line can fail.
        $this->inventory = $remainingStock;
        $this->changeReserve = $remainingReserve;
        $this->insertedCoins = CoinCollection::empty();

        $this->recordThat(new ProductDispensed($this->id, $selector, $price, $change));

        return DispensedGoods::of($product, $change);
    }

    /**
     * Drives the EXACT CHANGE ONLY lamp: the till holds nothing it is allowed
     * to hand back, so overpaying can only end in a refused sale.
     *
     * Deliberately narrow. Predicting whether some future overpayment could be
     * covered would need a change policy and a definition of the worst case the
     * brief does not give; the refusal on the purchase itself stays the
     * authoritative answer, and this is the warning that comes before it.
     */
    public function requiresExactChange(): bool
    {
        return $this->changeReserve->dispensableOnly()->isEmpty();
    }

    /**
     * A service visit sets absolute values — it says what the machine stocks
     * and how much change it holds, rather than adding to what was there. The
     * brief describes it as "set the available change and how many items we
     * have", and topping up would leave a technician unable to remove a
     * discontinued product.
     *
     * Any money a customer had inserted is returned first: someone opening the
     * machine does not get to keep it. Those coins go to the return tray, so
     * they do not join the reserve the technician just loaded.
     */
    public function service(Inventory $inventory, CoinCollection $changeReserve): void
    {
        $this->returnInsertedCoins();

        $this->inventory = $inventory;
        $this->changeReserve = $changeReserve;

        $this->recordThat(new MachineServiced($this->id, $inventory, $changeReserve));
    }

    public function id(): MachineId
    {
        return $this->id;
    }

    public function inventory(): Inventory
    {
        return $this->inventory;
    }

    public function changeReserve(): CoinCollection
    {
        return $this->changeReserve;
    }

    public function insertedCoins(): CoinCollection
    {
        return $this->insertedCoins;
    }

    public function insertedAmount(): Money
    {
        return $this->insertedCoins->total();
    }

    /**
     * How many times this machine has been written. Read by the persistence
     * adapter and by the tests that prove a lost update is caught; nothing in
     * the model has any business asking.
     */
    public function version(): int
    {
        return $this->version;
    }
}
