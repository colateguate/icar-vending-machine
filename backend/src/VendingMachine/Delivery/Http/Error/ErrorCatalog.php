<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use App\VendingMachine\Domain\Exception\InvalidProductSelector;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use InvalidArgumentException;

/**
 * The entire error contract of the API, as a table you can read in one screen.
 *
 * It is data rather than a match statement inside the subscriber for two
 * reasons. Reading it answers "what can this API answer, and with what?"
 * without following any control flow — the same question an integrator asks —
 * and a test can walk the domain's named failures and prove none of them is
 * missing, which is not something a buried conditional lets you do.
 *
 * The statuses follow one rule, keyed on whose problem it is:
 *
 *   422 — the value you sent is not valid input
 *   409 — the value is valid, and conflicts with the state of the machine
 *   404 — you named something that does not exist here
 *   400 — we could not read the request at all
 *   503 — the machine is not ready, and that is our problem rather than yours
 *
 * Anything absent from this table is, by definition, something we did not
 * anticipate: a 500 with nothing said about it (see ProblemType::internalError).
 */
final class ErrorCatalog
{
    /**
     * @var array<string, array{status: int, code: string, title: string}>
     */
    private const PROBLEMS = [
        // The value you sent is not valid input.
        UnsupportedCoin::class => ['status' => 422, 'code' => 'unsupported_coin', 'title' => 'Unsupported coin'],
        InvalidMoneyAmount::class => ['status' => 422, 'code' => 'invalid_money_amount', 'title' => 'Invalid amount'],
        InvalidProductSelector::class => ['status' => 422, 'code' => 'invalid_product_selector', 'title' => 'Invalid product selector'],
        InvalidRequestPayload::class => ['status' => 422, 'code' => 'invalid_request_payload', 'title' => 'Invalid request payload'],

        // You named something this machine does not have.
        UnknownProductSelector::class => ['status' => 404, 'code' => 'unknown_product', 'title' => 'Unknown product'],

        // Valid, and impossible right now given how the machine stands.
        ProductOutOfStock::class => ['status' => 409, 'code' => 'product_out_of_stock', 'title' => 'Product sold out'],
        InsufficientFunds::class => ['status' => 409, 'code' => 'insufficient_funds', 'title' => 'Insufficient funds'],
        CannotDispenseChange::class => ['status' => 409, 'code' => 'exact_change_required', 'title' => 'Exact change required'],

        // We could not read the request.
        MalformedJson::class => ['status' => 400, 'code' => 'malformed_json', 'title' => 'Malformed JSON body'],

        // Ours, not yours.
        MachineNotFound::class => ['status' => 503, 'code' => 'machine_not_provisioned', 'title' => 'Machine not provisioned'],
    ];

    public static function knows(string $error): bool
    {
        return isset(self::PROBLEMS[$error]);
    }

    public static function of(string $error): ProblemType
    {
        $problem = self::PROBLEMS[$error] ?? throw new InvalidArgumentException(\sprintf('%s is not a catalogued failure; ask knows() first.', $error));

        return new ProblemType($problem['status'], $problem['code'], $problem['title']);
    }
}
