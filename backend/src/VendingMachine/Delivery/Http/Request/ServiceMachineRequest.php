<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Request;

use App\VendingMachine\Application\Command\ServiceMachine\ServiceMachineCommand;
use App\VendingMachine\Delivery\Http\Error\InvalidRequestPayload;
use App\VendingMachine\Domain\Money\Money;
use Symfony\Component\HttpFoundation\Request;

/**
 * The technician's payload, and the reason this layer validates shape at all.
 *
 * {
 *   "products": [{"selector": "WATER", "name": "Water", "price": "0.65", "count": 10}],
 *   "changeReserve": [{"denomination": "0.25", "count": 4}]
 * }
 *
 * The command it builds declares this shape in its PHPDoc, which the analyser
 * checks on our side of the wire and cannot check on the client's. Without the
 * reading below, a missing key would reach the handler and die on a TypeError:
 * a 500 blaming the server for a mistake the client made. Everything here
 * therefore answers 422 before a command object exists.
 *
 * Two of the checks are not about shape but about the domain's invariants. A
 * repeated selector or a repeated denomination would reach a constructor that
 * treats it as a broken invariant — our bug, a 500 — because inside the model
 * it can only be one. On this side of the wire it is a client typing the same
 * row twice, so it is refused here and named.
 *
 * Coin denominations arrive as decimal strings like every other amount in this
 * API, and become the cents the command carries. Whether 0.02 is a coin the
 * machine takes is not asked here: CoinDenomination already answers it, and
 * answering it twice is how two answers start to differ.
 */
final readonly class ServiceMachineRequest
{
    /**
     * @param list<array{selector: string, name: string, price: string, count: int}> $products
     * @param array<int, int>                                                        $changeReserve denomination in cents => how many
     */
    private function __construct(private array $products, private array $changeReserve)
    {
    }

    public static function of(Request $request): self
    {
        $body = JsonBody::of($request);

        return new self(self::productsIn($body), self::changeReserveIn($body));
    }

    public function toCommand(): ServiceMachineCommand
    {
        return new ServiceMachineCommand($this->products, $this->changeReserve);
    }

    /**
     * @return list<array{selector: string, name: string, price: string, count: int}>
     */
    private static function productsIn(JsonBody $body): array
    {
        $products = [];

        foreach ($body->objectList('products') as $item) {
            $selector = $item->string('selector');

            if (isset($products[$selector])) {
                throw InvalidRequestPayload::duplicated('products', \sprintf('the selector "%s"', $selector));
            }

            $products[$selector] = [
                'selector' => $selector,
                'name' => $item->string('name'),
                'price' => $item->string('price'),
                'count' => $item->nonNegativeInt('count'),
            ];
        }

        return array_values($products);
    }

    /**
     * @return array<int, int>
     */
    private static function changeReserveIn(JsonBody $body): array
    {
        $reserve = [];

        foreach ($body->objectList('changeReserve') as $item) {
            $denomination = Money::fromDecimalString($item->string('denomination'))->cents();

            if (isset($reserve[$denomination])) {
                throw InvalidRequestPayload::duplicated('changeReserve', \sprintf('the denomination "%s"', $item->string('denomination')));
            }

            $reserve[$denomination] = $item->nonNegativeInt('count');
        }

        return $reserve;
    }
}
