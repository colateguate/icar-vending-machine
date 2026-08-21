<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Http\Error;

use App\Tests\Support\Reflection\FailureClasses;
use App\VendingMachine\Delivery\Http\Error\ErrorCatalog;
use App\VendingMachine\Delivery\Http\Error\InvalidRequestPayload;
use App\VendingMachine\Delivery\Http\Error\MalformedJson;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\CoinNotAccepted;
use App\VendingMachine\Domain\Exception\ConcurrentMachineModification;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use App\VendingMachine\Domain\Exception\InvalidProductSelector;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Exception\ProductOutOfStock;
use App\VendingMachine\Domain\Exception\UnknownProductSelector;
use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The catalog is the whole error contract of the API written down as data, so
 * these tests are the whole contract too: one says the map is complete, one
 * says the rule is stated for every entry in it, and one says each entry is
 * the status that rule demands.
 *
 * None of them needs a kernel, a repository or a request. The question they
 * answer is "does this table say the right thing", which is answered by
 * reading code — so they live in the fast suite even though the class under
 * test belongs to the delivery layer.
 */
final class ErrorCatalogTest extends TestCase
{
    /**
     * The reason this test exists: a domain error nobody catalogued still
     * reaches the client, as a 500 that blames the server for something the
     * domain named and anticipated. Adding an exception is easy to remember;
     * adding it to a table in another layer is not, so the suite remembers
     * instead.
     */
    public function test_every_named_domain_failure_is_catalogued(): void
    {
        $errors = FailureClasses::namedByTheDomain();

        self::assertNotEmpty($errors, 'no domain errors were found at all — this test is looking in the wrong place');

        foreach ($errors as $error) {
            self::assertTrue(
                ErrorCatalog::knows($error),
                \sprintf('%s implements VendingMachineError but has no entry in the catalog, so it would leave as a 500.', $error),
            );
        }
    }

    /**
     * The rule, one row per failure: 422 says the value you sent is not valid
     * input, 409 says it is valid but conflicts with the state of the machine,
     * 404 says you named something that does not exist here, 400 says we could
     * not read the request at all, and 503 says the machine is not ready —
     * which is our problem, not the caller's.
     *
     * @param class-string $error
     */
    #[DataProvider('theStatusRule')]
    public function test_it_answers_the_status_the_rule_demands(string $error, int $status, string $code): void
    {
        $problem = ErrorCatalog::of($error);

        self::assertSame($status, $problem->status);
        self::assertSame($code, $problem->code);
    }

    /**
     * @return iterable<string, array{class-string, int, string}>
     */
    public static function theStatusRule(): iterable
    {
        yield 'a coin no machine can read is not valid input' => [UnsupportedCoin::class, 422, 'unsupported_coin'];
        yield 'a coin this machine has been switched off for is not valid input' => [CoinNotAccepted::class, 422, 'coin_not_accepted'];
        yield 'a price that is not an amount is not valid input' => [InvalidMoneyAmount::class, 422, 'invalid_money_amount'];
        yield 'a selector that is not an identifier is not valid input' => [InvalidProductSelector::class, 422, 'invalid_product_selector'];
        yield 'a field of the wrong type is not valid input' => [InvalidRequestPayload::class, 422, 'invalid_request_payload'];
        yield 'a product this machine does not stock does not exist' => [UnknownProductSelector::class, 404, 'unknown_product'];
        yield 'an empty slot conflicts with the state' => [ProductOutOfStock::class, 409, 'product_out_of_stock'];
        yield 'too little money conflicts with the state' => [InsufficientFunds::class, 409, 'insufficient_funds'];
        yield 'change that cannot be composed conflicts with the state' => [CannotDispenseChange::class, 409, 'exact_change_required'];
        yield 'a machine that moved while you were deciding conflicts with the state' => [ConcurrentMachineModification::class, 409, 'concurrent_modification'];
        yield 'a body that is not JSON never became a value we could judge' => [MalformedJson::class, 400, 'malformed_json'];
        yield 'a machine that was never provisioned is our fault' => [MachineNotFound::class, 503, 'machine_not_provisioned'];
    }

    /**
     * The rule above is stated failure by failure, by hand, and it has to stay
     * that way: a provider generated from the catalog would assert that the
     * catalog agrees with itself, which is true of any table and proves
     * nothing. What it must not do is quietly cover fewer failures than the
     * catalog holds — which is exactly what happened, and how the two refusals
     * of the request itself went a whole release without a row.
     *
     * So this compares who the rule names against who the catalog holds, and
     * nothing else. The statuses stay written out above, independent of the
     * table they check.
     */
    public function test_the_rule_is_stated_for_every_catalogued_failure(): void
    {
        $stated = [];

        foreach (self::theStatusRule() as [$error]) {
            $stated[] = $error;
        }

        self::assertEqualsCanonicalizing(
            FailureClasses::catalogued(),
            $stated,
            'the catalog and the status rule name different failures — a new entry needs its own row above, saying which status the rule demands and why.',
        );
    }

    public function test_it_does_not_know_what_it_was_not_told(): void
    {
        self::assertFalse(ErrorCatalog::knows(RuntimeException::class));
    }
}
