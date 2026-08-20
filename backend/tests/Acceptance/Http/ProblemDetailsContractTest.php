<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every way this API can say no, and the promise that it always says it the
 * same way.
 *
 * The endpoint tests each assert their own statuses; this one asserts the
 * envelope those statuses arrive in — RFC 7807 problem+json, the same five
 * members every time, including for the failures Symfony raises before a
 * controller is ever reached. An API whose happy path is JSON and whose 404 is
 * an HTML page has two contracts, and clients discover the second one in
 * production.
 */
final class ProblemDetailsContractTest extends ApiTestCase
{
    public function test_a_domain_refusal_carries_the_full_problem_document(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(
            [
                'type' => '/problems/insufficient-funds',
                'title' => 'Insufficient funds',
                'status' => 409,
                'detail' => 'Another 0.40 is needed before this product can be dispensed.',
                'code' => 'insufficient_funds',
                'missingAmount' => '0.40',
            ],
            $this->responseBody(),
        );
    }

    /**
     * The status member of the document and the status line of the response
     * are the same number. They are written in two different places, so it is
     * worth one assertion that they cannot drift.
     */
    public function test_the_status_member_matches_the_http_status(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/purchases', ['selector' => 'water']);

        self::assertSame(
            $this->client->getResponse()->getStatusCode(),
            $this->responseBody()['status'],
        );
    }

    /**
     * Not 422: we never got as far as looking at the values. This is the one
     * error that is about the bytes rather than about their meaning.
     *
     * Asked of every operation that reads a body, because the contract
     * declares the 400 for every one of them, and a status the document
     * promises but no test provokes is a claim rather than a contract. The
     * three cases below are exactly the three request DTOs built through
     * JsonBody::of(), which is the only place this refusal is raised.
     */
    #[DataProvider('theOperationsThatReadABody')]
    public function test_a_body_that_is_not_json_is_a_400(string $method, string $uri): void
    {
        $this->givenAStockedMachine();

        $this->requestWithRawBody($method, $uri, '{"broken": ');

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('malformed_json', $this->responseBody()['code']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function theOperationsThatReadABody(): iterable
    {
        yield 'insert a coin' => ['POST', '/api/machine/coins'];
        yield 'buy a product' => ['POST', '/api/machine/purchases'];
        yield 'service the machine' => ['PUT', '/api/machine/service'];
    }

    /**
     * The fourth writing endpoint, and the reason it declares no 400:
     * RETURN-COIN takes no argument, so there is no body to fail at reading
     * and an unreadable one changes nothing. Asserting it keeps the omission
     * from being a story — the day this controller starts parsing a payload,
     * this test goes red and the contract needs the 400 the other three have.
     */
    public function test_the_endpoint_that_reads_no_body_is_unmoved_by_one_that_is_not_json(): void
    {
        $this->givenAStockedMachine();

        $this->requestWithRawBody('POST', '/api/machine/coins/return', '{"broken": ');

        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Parsing and being an object are two different promises, and all three
     * request bodies make the second one: their schemas declare type: object.
     * A payload can parse perfectly and still be a list, so the case is asked
     * of every operation rather than of the one that happened to have it.
     */
    #[DataProvider('theOperationsThatReadABody')]
    public function test_a_json_body_that_is_not_an_object_is_a_400(string $method, string $uri): void
    {
        $this->givenAStockedMachine();

        $this->requestWithRawBody($method, $uri, '[]');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('malformed_json', $this->responseBody()['code']);
    }

    public function test_an_unknown_route_is_a_problem_document_too(): void
    {
        $this->requestOutsideTheContract('GET', '/api/machine/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(404, $this->responseBody()['status']);
    }

    public function test_a_method_the_route_does_not_take_is_a_problem_document_too(): void
    {
        $this->requestOutsideTheContract('DELETE', '/api/machine');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
    }

    /**
     * Nobody named a machine that does not exist — the route is the singleton
     * /api/machine — so this is not a 404. The machine was never provisioned,
     * which is our problem, and 503 is the honest way to say "not ready yet".
     */
    public function test_an_unprovisioned_machine_is_a_503(): void
    {
        $this->request('GET', '/api/machine');

        self::assertResponseStatusCodeSame(503);
        self::assertSame('machine_not_provisioned', $this->responseBody()['code']);
    }

    public function test_every_endpoint_reports_the_unprovisioned_machine_the_same_way(): void
    {
        foreach ([
            ['POST', '/api/machine/coins', ['coin' => '0.25']],
            ['POST', '/api/machine/coins/return', null],
            ['POST', '/api/machine/purchases', ['selector' => 'WATER']],
            ['PUT', '/api/machine/service', ['products' => [], 'changeReserve' => []]],
        ] as [$method, $uri, $body]) {
            $this->request($method, $uri, $body);

            self::assertResponseStatusCodeSame(503, \sprintf('%s %s', $method, $uri));
            self::assertSame('machine_not_provisioned', $this->responseBody()['code']);
        }
    }

    /**
     * The detail of a domain refusal is the message the domain wrote, and
     * nothing else: no class name, no file path, no stack frame.
     */
    public function test_the_detail_of_a_refusal_says_nothing_about_the_code_that_raised_it(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'JUICE']);

        $detail = $this->responseBody()['detail'];
        self::assertIsString($detail);
        self::assertStringNotContainsString('App\\', $detail);
        self::assertStringNotContainsString('.php', $detail);
        self::assertStringNotContainsString('Exception', $detail);
    }
}
