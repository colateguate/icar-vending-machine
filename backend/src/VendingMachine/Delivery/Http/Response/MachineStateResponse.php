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
     *     supportedCoins: list<array{denomination: string, dispensableAsChange: bool, enabled: bool}>,
     *     exactChangeOnly: bool,
     *     outOfService: bool,
     * }
     */
    public static function from(MachineStateView $view): array
    {
        return [
            'products' => self::shelves($view),
            'changeReserve' => CoinsResponse::from($view->changeReserve),
            'insertedCoins' => CoinsResponse::from($view->insertedCoins),
            'acceptedCoins' => self::whatTheSlotTakes($view),
            'supportedCoins' => self::whatTheAcceptorReads($view),
            'exactChangeOnly' => $view->exactChangeOnly,
            'outOfService' => $view->outOfService,
        ];
    }

    /**
     * @return list<array{selector: string, name: string, price: string, count: int}>
     */
    private static function shelves(MachineStateView $view): array
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

        return $products;
    }

    /**
     * @return list<array{denomination: string, dispensableAsChange: bool}>
     */
    private static function whatTheSlotTakes(MachineStateView $view): array
    {
        $coins = [];
        foreach ($view->acceptedCoins as $coin) {
            $coins[] = [
                'denomination' => $coin->amount()->toDecimalString(),
                'dispensableAsChange' => $coin->isDispensableAsChange(),
            ];
        }

        return $coins;
    }

    /**
     * Every coin the acceptor can read, and whether this machine is taking it.
     * The overlap with the list above is deliberate: that one answers the
     * customer's question — what may I put in — and is the shape clients
     * already read, while this one answers the technician's, and is the only
     * list that can show a coin the machine is refusing.
     *
     * @return list<array{denomination: string, dispensableAsChange: bool, enabled: bool}>
     */
    private static function whatTheAcceptorReads(MachineStateView $view): array
    {
        $coins = [];
        foreach ($view->supportedCoins as $coin) {
            $coins[] = [
                'denomination' => $coin->amount()->toDecimalString(),
                'dispensableAsChange' => $coin->isDispensableAsChange(),
                'enabled' => \in_array($coin, $view->acceptedCoins, true),
            ];
        }

        return $coins;
    }
}
