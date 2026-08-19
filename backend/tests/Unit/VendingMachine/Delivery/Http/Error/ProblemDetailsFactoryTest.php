<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Http\Error;

use App\VendingMachine\Delivery\Http\Error\ProblemDetailsFactory;
use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turning a thrown thing into the document a client reads.
 *
 * The interesting case is the last one: what the API says when it does not
 * know what went wrong. That is a security question as much as a design one,
 * so it is asserted rather than assumed.
 */
final class ProblemDetailsFactoryTest extends TestCase
{
    public function test_it_renders_a_catalogued_failure(): void
    {
        $problem = self::documentFor(UnsupportedCoin::ofCents(2));

        self::assertSame(
            [
                'type' => '/problems/unsupported-coin',
                'title' => 'Unsupported coin',
                'status' => 422,
                'detail' => 'The machine does not accept coins of 2 cents.',
                'code' => 'unsupported_coin',
            ],
            $problem,
        );
    }

    public function test_the_response_is_labelled_as_problem_json(): void
    {
        $response = (new ProblemDetailsFactory())->fromThrowable(UnsupportedCoin::ofCents(2));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    /**
     * The refusals that carry a number carry it into the document, so a client
     * can tell the customer how much is missing instead of parsing a sentence.
     */
    public function test_it_says_how_much_money_is_missing(): void
    {
        $problem = self::documentFor(InsufficientFunds::needsMore(Money::fromDecimalString('0.40')));

        self::assertSame('0.40', $problem['missingAmount']);
    }

    public function test_it_says_how_much_change_could_not_be_paid(): void
    {
        $problem = self::documentFor(CannotDispenseChange::forAmount(Money::fromDecimalString('0.35')));

        self::assertSame('0.35', $problem['changeDue']);
        self::assertSame('exact_change_required', $problem['code']);
    }

    /**
     * Anything the domain did not anticipate is our bug, not the caller's
     * mistake, and the caller learns nothing about it. The message of the
     * original exception is the thing most likely to name a class, a query or
     * a path, so it is the thing that must not survive.
     */
    public function test_it_suppresses_everything_about_an_unanticipated_failure(): void
    {
        $problem = self::documentFor(new RuntimeException('SQLSTATE[HY000] no such table: machines in /var/www/src/Repo.php'));

        $detail = $problem['detail'];
        self::assertIsString($detail);

        self::assertSame(500, $problem['status']);
        self::assertSame('internal_error', $problem['code']);
        self::assertStringNotContainsString('SQLSTATE', $detail);
        self::assertStringNotContainsString('/var/www', $detail);
        self::assertStringNotContainsString('machines', $detail);
    }

    /**
     * Symfony refuses some requests before a controller is reached — an
     * unknown route, a method the route does not take. They are part of the
     * API's error surface too, so they leave in the same envelope.
     */
    public function test_it_speaks_for_the_failures_the_framework_raises(): void
    {
        $problem = self::documentFor(new NotFoundHttpException('No route found for "GET /api/nope"'));

        self::assertSame(404, $problem['status']);
        self::assertSame('not_found', $problem['code']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function documentFor(Throwable $error): array
    {
        $content = (new ProblemDetailsFactory())->fromThrowable($error)->getContent();
        self::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /* @var array<string, mixed> */
        return $decoded;
    }
}
