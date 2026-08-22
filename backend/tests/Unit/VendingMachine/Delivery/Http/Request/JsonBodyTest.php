<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Http\Request;

use App\VendingMachine\Delivery\Http\Error\InvalidRequestPayload;
use App\VendingMachine\Delivery\Http\Error\MalformedJson;
use App\VendingMachine\Delivery\Http\Request\JsonBody;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The rules that decide whether a request body has the shape a command
 * declares. The endpoint tests assert that breaking them produces a 422; this
 * asserts what "breaking them" means, one rule at a time, without a kernel in
 * the way.
 *
 * Every failure names the field it is about, including inside a nested list,
 * because "invalid payload" tells a technician's UI nothing about which input
 * to put a red border around.
 */
final class JsonBodyTest extends TestCase
{
    public function test_it_reads_a_string(): void
    {
        self::assertSame('0.25', self::body('{"coin": "0.25"}')->string('coin'));
    }

    public function test_a_missing_field_names_itself(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('coin');

        self::body('{}')->string('coin');
    }

    /**
     * A JSON number where the contract asks for a decimal string is the one
     * mistake this API most wants to catch: it is how floating point gets back
     * into money.
     */
    public function test_a_number_is_not_a_string(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('coin');

        self::body('{"coin": 0.25}')->string('coin');
    }

    public function test_a_null_is_not_a_string(): void
    {
        $this->expectException(InvalidRequestPayload::class);

        self::body('{"coin": null}')->string('coin');
    }

    public function test_it_reads_a_count(): void
    {
        self::assertSame(0, self::body('{"count": 0}')->nonNegativeInt('count'));
    }

    public function test_a_numeric_string_is_not_a_count(): void
    {
        $this->expectException(InvalidRequestPayload::class);

        self::body('{"count": "4"}')->nonNegativeInt('count');
    }

    /**
     * Negative counts are refused here rather than deeper down, where they
     * break an invariant and become a 500 — the machine cannot hold minus one
     * bottle, so nothing below has a reason to expect the question.
     */
    public function test_a_negative_count_is_refused(): void
    {
        $this->expectException(InvalidRequestPayload::class);

        self::body('{"count": -1}')->nonNegativeInt('count');
    }

    public function test_it_reads_a_list_of_objects(): void
    {
        $items = self::body('{"products": [{"name": "Water"}, {"name": "Soda"}]}')->objectList('products');

        self::assertCount(2, $items);
        self::assertSame('Soda', $items[1]->string('name'));
    }

    public function test_an_empty_list_is_a_list(): void
    {
        self::assertSame([], self::body('{"products": []}')->objectList('products'));
    }

    public function test_a_string_is_not_a_list(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('products');

        self::body('{"products": "WATER"}')->objectList('products');
    }

    public function test_an_object_is_not_a_list(): void
    {
        $this->expectException(InvalidRequestPayload::class);

        self::body('{"products": {"0": {"name": "Water"}}}')->objectList('products');
    }

    public function test_a_list_of_strings_is_not_a_list_of_objects(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('products[0]');

        self::body('{"products": ["WATER"]}')->objectList('products');
    }

    /**
     * The path is what makes the message worth reading.
     */
    public function test_a_failure_inside_a_list_names_the_element_and_the_field(): void
    {
        $items = self::body('{"products": [{"count": 1}, {"count": "2"}]}')->objectList('products');

        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('products[1].count');

        $items[1]->nonNegativeInt('count');
    }

    public function test_it_reads_a_list_of_strings(): void
    {
        self::assertSame(
            ['0.05', '1.00'],
            self::body('{"acceptedCoins": ["0.05", "1.00"]}')->stringList('acceptedCoins'),
        );
    }

    public function test_an_empty_list_of_strings_is_a_list(): void
    {
        self::assertSame([], self::body('{"acceptedCoins": []}')->stringList('acceptedCoins'));
    }

    public function test_a_string_is_not_a_list_of_strings(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('acceptedCoins');

        self::body('{"acceptedCoins": "0.05"}')->stringList('acceptedCoins');
    }

    /**
     * The index is the whole value of the message: a client told only "invalid
     * payload" has to guess which of six coins it got wrong.
     */
    public function test_an_element_that_is_not_a_string_names_its_index(): void
    {
        $this->expectException(InvalidRequestPayload::class);
        $this->expectExceptionMessage('acceptedCoins[1]');

        self::body('{"acceptedCoins": ["0.05", 1.00]}')->stringList('acceptedCoins');
    }

    public function test_it_knows_which_fields_the_caller_mentioned(): void
    {
        $body = self::body('{"acceptedCoins": []}');

        self::assertTrue($body->has('acceptedCoins'));
        self::assertFalse($body->has('products'));
    }

    /**
     * The trap this method exists for. A field sent as null is a field the
     * caller mentioned, so `has()` says yes and the reader that follows gets to
     * refuse the value — rather than null being silently indistinguishable from
     * having said nothing at all, which is a different instruction.
     */
    public function test_a_field_sent_as_null_still_counts_as_mentioned(): void
    {
        self::assertTrue(self::body('{"acceptedCoins": null}')->has('acceptedCoins'));
    }

    public function test_a_body_that_is_not_json_is_malformed(): void
    {
        $this->expectException(MalformedJson::class);

        self::body('{"coin": ');
    }

    /**
     * A bare string is valid JSON and still not a request body: there are no
     * fields to read out of it.
     */
    public function test_a_body_that_is_not_an_object_is_malformed(): void
    {
        $this->expectException(MalformedJson::class);

        self::body('"0.25"');
    }

    public function test_an_empty_body_is_malformed(): void
    {
        $this->expectException(MalformedJson::class);

        self::body('');
    }

    private static function body(string $content): JsonBody
    {
        return JsonBody::of(Request::create('/api/machine/coins', 'POST', content: $content));
    }
}
