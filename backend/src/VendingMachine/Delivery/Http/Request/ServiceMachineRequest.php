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
     * @param list<int>|null                                                         $acceptedCoins denominations in cents, or null when
     *                                                                                              the visit did not mention the acceptor
     */
    private function __construct(
        private array $products,
        private array $changeReserve,
        private ?array $acceptedCoins,
    ) {
    }

    public static function of(Request $request): self
    {
        $body = JsonBody::of($request);

        return new self(self::productsIn($body), self::changeReserveIn($body), self::acceptedCoinsIn($body));
    }

    public function toCommand(): ServiceMachineCommand
    {
        return new ServiceMachineCommand($this->products, $this->changeReserve, $this->acceptedCoins);
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
     * Which denominations the machine takes from now on — a set, so it travels
     * as a plain list of amounts rather than as a flag on the till rows beside
     * it. The two answer different questions: the reserve says how many coins
     * are in there, this says which ones the slot will read, and a denomination
     * can appear in either without the other. Money already inside when its
     * denomination is switched off shows up as exactly that: a row in the
     * reserve that is not in this list.
     *
     * Absent means the visit did not touch the acceptor. An empty list means it
     * takes nothing at all from now on, which is a machine out of service —
     * a state a technician is allowed to leave behind (ADR-0018).
     *
     * Repeats are read rather than refused, unlike a repeated till row. There a
     * duplicate is ambiguous — four coins or two? — and here it cannot be: a
     * set holds a denomination once however many times you name it.
     *
     * @return list<int>|null
     */
    private static function acceptedCoinsIn(JsonBody $body): ?array
    {
        if (!$body->has('acceptedCoins')) {
            return null;
        }

        return array_map(
            static fn (string $denomination): int => Money::fromDecimalString($denomination)->cents(),
            $body->stringList('acceptedCoins'),
        );
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
