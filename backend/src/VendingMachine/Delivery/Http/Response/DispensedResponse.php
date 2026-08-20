<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Response;

use App\VendingMachine\Domain\Dispensing\DispensedGoods;

/**
 * What fell into the tray: the item, and the coins that came with it.
 *
 * This is in the response because it cannot be asked for later — the product
 * and the change have physically left the machine, and no query about the
 * machine's state could tell a client which coins those were.
 */
final class DispensedResponse
{
    /**
     * @return array{
     *     selector: string,
     *     name: string,
     *     price: string,
     *     change: array{coins: list<array{denomination: string, count: int}>, amount: string},
     * }
     */
    public static function from(DispensedGoods $dispensed): array
    {
        return [
            'selector' => $dispensed->selector()->value(),
            'name' => $dispensed->name(),
            'price' => $dispensed->price()->toDecimalString(),
            'change' => CoinsResponse::from($dispensed->change()),
        ];
    }
}
