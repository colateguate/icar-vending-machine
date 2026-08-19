<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Machine;

use App\Shared\Domain\AggregateRoot;
use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Event\CoinInserted;
use App\VendingMachine\Domain\Event\CoinsRefunded;
use App\VendingMachine\Domain\Event\MachineServiced;
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
}
