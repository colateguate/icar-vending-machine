<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Response;

use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;

/**
 * A bag of coins on the wire: the coins themselves, and what they add up to.
 *
 * The total is sent rather than left to the client to compute, because a
 * client that adds up money is a client doing arithmetic on amounts it parsed
 * — and the parsing is exactly what decimal strings exist to prevent.
 *
 * Denominations are decimal strings like every other amount here, not the
 * cents the model counts in. One unit on the wire, and the edge is where the
 * translation happens.
 */
final class CoinsResponse
{
    /**
     * @return array{coins: list<array{denomination: string, count: int}>, amount: string}
     */
    public static function from(CoinCollection $coins): array
    {
        $entries = [];
        foreach ($coins->toArray() as $cents => $count) {
            $entries[] = [
                'denomination' => Money::fromCents($cents)->toDecimalString(),
                'count' => $count,
            ];
        }

        return [
            'coins' => $entries,
            'amount' => $coins->total()->toDecimalString(),
        ];
    }
}
