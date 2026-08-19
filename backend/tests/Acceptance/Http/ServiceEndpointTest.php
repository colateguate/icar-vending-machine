<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PUT /api/machine/service — the technician's endpoint, and the one with a
 * payload complex enough to be worth validating before it reaches a handler.
 *
 * PUT rather than POST because the visit is idempotent by definition: it sets
 * what the machine stocks and holds, so sending it twice leaves the same
 * machine.
 */
final class ServiceEndpointTest extends ApiTestCase
{
    public function test_it_replaces_the_catalogue_and_the_till(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [
                ['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4],
            ],
            'changeReserve' => [
                ['denomination' => '0.25', 'count' => 2],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4]],
            $this->machineState()['products'],
            'a service visit sets, it does not top up',
        );
        self::assertSame('0.50', $this->responseValue('machine', 'changeReserve', 'amount'));
    }

    /**
     * Someone opening the machine does not get to keep what a customer put in.
     */
    public function test_it_gives_back_money_a_customer_had_inserted(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [['denomination' => '0.05', 'count' => 3]],
        ]);

        self::assertSame(['coins' => [], 'amount' => '0.00'], $this->machineState()['insertedCoins']);
        self::assertSame('0.15', $this->responseValue('machine', 'changeReserve', 'amount'), 'the escrow does not join the till');
    }

    public function test_it_accepts_emptying_the_machine(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', ['products' => [], 'changeReserve' => []]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->machineState()['products']);
        self::assertTrue($this->machineState()['exactChangeOnly']);
    }

    /**
     * The blocking rule of this endpoint: a payload that does not have the
     * shape the command declares is answered before a command object exists.
     *
     * Every one of these would otherwise reach the handler and die on a
     * TypeError or on a broken invariant — a 500 blaming the server for a
     * mistake the client made. Asserting the status is 422 is therefore also
     * asserting it is never 500.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('malformedPayloads')]
    public function test_it_answers_422_for_a_payload_of_the_wrong_shape(array $payload): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('invalid_request_payload', $this->responseBody()['code']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedPayloads(): iterable
    {
        $product = ['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4];
        $reserve = [['denomination' => '0.25', 'count' => 2]];

        yield 'products missing' => [['changeReserve' => $reserve]];
        yield 'changeReserve missing' => [['products' => [$product]]];
        yield 'products is not a list' => [['products' => 'TEA', 'changeReserve' => $reserve]];
        yield 'a product is not an object' => [['products' => ['TEA'], 'changeReserve' => $reserve]];
        yield 'changeReserve is a map, not a list' => [['products' => [], 'changeReserve' => ['25' => 2]]];
        yield 'count is a string' => [['products' => [[...$product, 'count' => '4']], 'changeReserve' => $reserve]];
        yield 'price is a number' => [['products' => [[...$product, 'price' => 0.8]], 'changeReserve' => $reserve]];
        yield 'name is missing' => [['products' => [['selector' => 'TEA', 'price' => '0.80', 'count' => 4]], 'changeReserve' => $reserve]];
        yield 'selector is a number' => [['products' => [[...$product, 'selector' => 7]], 'changeReserve' => $reserve]];
        yield 'a negative count' => [['products' => [[...$product, 'count' => -1]], 'changeReserve' => $reserve]];
        yield 'a negative coin count' => [['products' => [], 'changeReserve' => [['denomination' => '0.25', 'count' => -2]]]];
        yield 'the same selector twice' => [['products' => [$product, $product], 'changeReserve' => $reserve]];
        yield 'the same denomination twice' => [['products' => [], 'changeReserve' => [['denomination' => '0.25', 'count' => 1], ['denomination' => '0.25', 'count' => 2]]]];
        yield 'a coin entry without a count' => [['products' => [], 'changeReserve' => [['denomination' => '0.25']]]];
    }

    /**
     * These are well-shaped payloads carrying values the domain refuses. The
     * status is the same 422 — the client sent something invalid either way —
     * but the code names which value, which is what a technician's UI needs to
     * point at the offending field.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('payloadsWithValuesTheDomainRefuses')]
    public function test_it_answers_422_and_names_the_value_the_domain_refused(array $payload, string $expectedCode): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($expectedCode, $this->responseBody()['code']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function payloadsWithValuesTheDomainRefuses(): iterable
    {
        yield 'a selector that is not an identifier' => [
            ['products' => [['selector' => 'iced tea', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4]], 'changeReserve' => []],
            'invalid_product_selector',
        ];

        yield 'a price that is not an amount' => [
            ['products' => [['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => 'free', 'count' => 4]], 'changeReserve' => []],
            'invalid_money_amount',
        ];

        yield 'a coin the machine does not take' => [
            ['products' => [], 'changeReserve' => [['denomination' => '0.02', 'count' => 5]]],
            'unsupported_coin',
        ];
    }

    public function test_it_leaves_the_machine_untouched_when_the_payload_is_refused(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', ['products' => 'TEA']);
        $this->request('GET', '/api/machine');

        self::assertCount(3, $this->responseArray('machine', 'products'));
        self::assertSame('4.00', $this->responseValue('machine', 'changeReserve', 'amount'));
    }
}
