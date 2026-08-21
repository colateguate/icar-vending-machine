<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use App\VendingMachine\Domain\Money\CoinDenomination;
use DomainException;

/**
 * A coin the acceptor can read perfectly well and this machine has been told
 * not to take.
 *
 * Deliberately not UnsupportedCoin, which says something else: that no machine
 * of this model can read the piece at all. The two are told apart because the
 * answers differ — one is permanent and about the currency, the other is a
 * setting a technician can undo on the next visit — and a client that shows
 * "this machine does not take 0.50 today" is saying something true that
 * "unsupported coin" would not.
 */
final class CoinNotAccepted extends DomainException implements VendingMachineError
{
    public static function atTheSlot(CoinDenomination $denomination): self
    {
        return new self(\sprintf(
            'This machine is not currently taking %s coins.',
            $denomination->amount()->toDecimalString(),
        ));
    }
}
