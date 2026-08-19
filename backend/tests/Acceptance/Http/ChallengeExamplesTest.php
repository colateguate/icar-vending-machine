<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

/**
 * The three examples of the brief, driven over HTTP exactly as a client would.
 *
 * They are the acceptance criteria of the whole exercise, so they are written
 * as one test each, named after the example they encode, and they assert the
 * literal outcome the brief prints — SODA, two dimes back, water plus 0.25 and
 * 0.10. If any of these three fails, the machine does not do what it was asked
 * to do, whatever the rest of the suite says.
 *
 * The same three sequences also exist at unit level against the aggregate and
 * at integration level against the buses. That is deliberate rather than
 * duplication: those ask whether the rules and the wiring are right, this one
 * asks whether the promise the brief makes survives all the way out to JSON.
 */
final class ChallengeExamplesTest extends ApiTestCase
{
    /**
     * Example 1: 1, 0.25, 0.25, GET-SODA -> SODA.
     */
    public function test_example_1_buying_a_soda_with_exact_change(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
        $this->request('POST', '/api/machine/purchases', ['selector' => 'SODA']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [
                'selector' => 'SODA',
                'name' => 'Soda',
                'price' => '1.50',
                'change' => ['coins' => [], 'amount' => '0.00'],
            ],
            $this->responseBody()['dispensed'],
        );
    }

    /**
     * Example 2: 0.10, 0.10, RETURN-COIN -> 0.10, 0.10.
     */
    public function test_example_2_asking_for_the_coins_back(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);
        $this->request('POST', '/api/machine/coins/return');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [
                'coins' => [['denomination' => '0.10', 'count' => 2]],
                'amount' => '0.20',
            ],
            $this->responseBody()['returned'],
        );
    }

    /**
     * Example 3: 1, GET-WATER -> WATER, 0.25, 0.10.
     */
    public function test_example_3_buying_water_without_exact_change(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [
                'selector' => 'WATER',
                'name' => 'Water',
                'price' => '0.65',
                'change' => [
                    'coins' => [
                        ['denomination' => '0.10', 'count' => 1],
                        ['denomination' => '0.25', 'count' => 1],
                    ],
                    'amount' => '0.35',
                ],
            ],
            $this->responseBody()['dispensed'],
        );
    }
}
