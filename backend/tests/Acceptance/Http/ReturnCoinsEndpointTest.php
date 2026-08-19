<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

/**
 * POST /api/machine/coins/return — the RETURN-COIN button.
 *
 * The refunded coins are in the response because they have physically left the
 * machine: no later question could tell a client which ones fell into the
 * tray.
 */
final class ReturnCoinsEndpointTest extends ApiTestCase
{
    public function test_it_gives_back_the_very_coins_that_were_inserted(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.05']);

        $this->request('POST', '/api/machine/coins/return');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [
                'coins' => [
                    ['denomination' => '0.05', 'count' => 1],
                    ['denomination' => '0.25', 'count' => 1],
                ],
                'amount' => '0.30',
            ],
            $this->responseBody()['returned'],
        );
    }

    public function test_it_empties_the_escrow(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('POST', '/api/machine/coins/return');

        self::assertSame(['coins' => [], 'amount' => '0.00'], $this->machineState()['insertedCoins']);
    }

    /**
     * Pressing the button twice is not an error: the second press finds an
     * empty escrow and says so.
     */
    public function test_it_returns_nothing_when_no_coin_was_inserted(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins/return');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['coins' => [], 'amount' => '0.00'], $this->responseBody()['returned']);
    }

    /**
     * The button takes no argument, so a body is neither required nor read.
     */
    public function test_it_ignores_a_body(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);

        $this->request('POST', '/api/machine/coins/return', ['whatever' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('0.10', $this->responseValue('returned', 'amount'));
    }
}
