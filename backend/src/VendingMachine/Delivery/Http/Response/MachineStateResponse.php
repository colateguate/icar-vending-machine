<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Response;

use App\VendingMachine\Application\Query\GetMachineState\MachineStateView;

/**
 * Everything a client can know about the machine, as JSON.
 *
 * This is where domain values become wire values, and it is the only place
 * that happens: the read model hands over Money and CoinCollection precisely
 * so that one delivery mechanism's conventions — decimal strings, this key
 * order, this word for "how many are left" — stay on this side of the
 * application layer.
 *
 * exactChangeOnly is the lamp on the front of the machine. It is part of the
 * state so a client can warn before taking someone's money, rather than
 * discovering it in a refused sale.
 */
final class MachineStateResponse
{
    /**
     * @return array{
     *     products: list<array{selector: string, name: string, price: string, count: int}>,
     *     changeReserve: array{coins: list<array{denomination: string, count: int}>, amount: string},
     *     insertedCoins: array{coins: list<array{denomination: string, count: int}>, amount: string},
     *     acceptedCoins: list<array{denomination: string, dispensableAsChange: bool}>,
     *     exactChangeOnly: bool,
     * }
     */
    public static function from(MachineStateView $view): array
    {
        $products = [];
        foreach ($view->products as $product) {
            $products[] = [
                'selector' => $product->selector()->value(),
                'name' => $product->name(),
                'price' => $product->price()->toDecimalString(),
                'count' => $product->available()->units(),
            ];
        }

        $acceptedCoins = [];
        foreach ($view->acceptedCoins as $coin) {
            $acceptedCoins[] = [
                'denomination' => $coin->amount()->toDecimalString(),
                'dispensableAsChange' => $coin->isDispensableAsChange(),
            ];
        }

        return [
            'products' => $products,
            'changeReserve' => CoinsResponse::from($view->changeReserve),
            'insertedCoins' => CoinsResponse::from($view->insertedCoins),
            'acceptedCoins' => $acceptedCoins,
            'exactChangeOnly' => $view->exactChangeOnly,
        ];
    }
}
