<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineState;

use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Domain\Money\Money;

/**
 * Everything a client can know about the machine, and nothing it could use to
 * change it.
 *
 * The read side deliberately does not hand back the aggregate. Doing so would
 * put purchase() within reach of a controller, and the whole point of the
 * command side is that changes go through it.
 *
 * It carries domain values rather than strings: formatting money for the wire
 * is the edge's job, and doing it here would bake one delivery mechanism's
 * conventions into the application layer.
 */
final readonly class MachineStateView
{
    /**
     * @param list<Product>          $products
     * @param list<CoinDenomination> $acceptedCoins  Which coins the slot takes,
     *                                               smallest first. Not the same
     *                                               question as which coins the
     *                                               machine currently holds.
     * @param list<CoinDenomination> $supportedCoins Which coins the acceptor can
     *                                               read at all. Never narrower
     *                                               than $acceptedCoins, and
     *                                               wider whenever a technician
     *                                               has switched one off.
     */
    public function __construct(
        public array $products,
        public CoinCollection $changeReserve,
        public CoinCollection $insertedCoins,
        public Money $insertedAmount,
        public array $acceptedCoins,
        public array $supportedCoins,
        public bool $exactChangeOnly,
        public bool $outOfService,
    ) {
    }
}
