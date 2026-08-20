<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Http\Error;

use App\Tests\Support\Reflection\FailureClasses;
use App\VendingMachine\Delivery\Http\Error\ErrorCatalog;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
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
 * these two tests are the whole contract too: one says the map is complete,
 * the other says each entry is the status the rule demands.
 *
 * Neither needs a kernel, a repository or a request. The question they answer
 * is "does this table say the right thing", which is answered by reading code
 * — so they live in the fast suite even though the class under test belongs to
 * the delivery layer.
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
     * 404 says you named something that does not exist here, and 503 says the
     * machine is not ready — which is our problem, not the caller's.
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
        yield 'a coin the machine does not take is not valid input' => [UnsupportedCoin::class, 422, 'unsupported_coin'];
        yield 'a price that is not an amount is not valid input' => [InvalidMoneyAmount::class, 422, 'invalid_money_amount'];
        yield 'a selector that is not an identifier is not valid input' => [InvalidProductSelector::class, 422, 'invalid_product_selector'];
        yield 'a product this machine does not stock does not exist' => [UnknownProductSelector::class, 404, 'unknown_product'];
        yield 'an empty slot conflicts with the state' => [ProductOutOfStock::class, 409, 'product_out_of_stock'];
        yield 'too little money conflicts with the state' => [InsufficientFunds::class, 409, 'insufficient_funds'];
        yield 'change that cannot be composed conflicts with the state' => [CannotDispenseChange::class, 409, 'exact_change_required'];
        yield 'a machine that was never provisioned is our fault' => [MachineNotFound::class, 503, 'machine_not_provisioned'];
    }

    public function test_it_does_not_know_what_it_was_not_told(): void
    {
        self::assertFalse(ErrorCatalog::knows(RuntimeException::class));
    }
}
