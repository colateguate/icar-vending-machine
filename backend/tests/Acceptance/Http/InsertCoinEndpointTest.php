<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

/**
 * POST /api/machine/coins — one coin per call, and the new state comes back
 * with it so a client never has to ask a second time to redraw itself.
 */
final class InsertCoinEndpointTest extends ApiTestCase
{
    public function test_it_accepts_a_coin_and_answers_with_the_running_total(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('0.25', $this->responseValue('machine', 'insertedCoins', 'amount'));
    }

    public function test_it_accumulates_across_calls(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.05']);

        self::assertSame('1.05', $this->responseValue('machine', 'insertedCoins', 'amount'));
    }

    /**
     * A 0.02 piece is a perfectly valid amount of money that this machine does
     * not take. The value is wrong, not the state, so it is a 422.
     */
    public function test_it_rejects_a_coin_the_machine_does_not_accept(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '0.02']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('unsupported_coin', $this->responseBody()['code']);
    }

    /**
     * The other half of the same 422, and the distinction the whole coin
     * configuration rests on: a 0.50 piece is a coin the hardware reads
     * perfectly well and this machine has been switched off for. Two different
     * situations get two different codes, so a client can say which one
     * happened.
     */
    public function test_it_rejects_a_coin_this_machine_has_been_switched_off_for(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '0.50']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('coin_not_accepted', $this->responseBody()['code']);
    }

    public function test_it_rejects_an_amount_that_is_not_money(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => 'a lot']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_money_amount', $this->responseBody()['code']);
    }

    /**
     * A JSON number would put the client back on the floating point we spent
     * the whole domain avoiding, so the contract does not take one.
     */
    public function test_it_rejects_a_numeric_coin(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => 0.25]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_request_payload', $this->responseBody()['code']);
    }

    public function test_it_rejects_a_body_without_a_coin(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['amount' => '0.25']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_request_payload', $this->responseBody()['code']);
    }
}
