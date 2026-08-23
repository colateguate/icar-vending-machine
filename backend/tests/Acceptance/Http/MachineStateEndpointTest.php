<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;

/**
 * GET /api/machine — the whole read side of the API in one document.
 */
final class MachineStateEndpointTest extends ApiTestCase
{
    public function test_it_describes_the_machine_a_customer_is_standing_in_front_of(): void
    {
        $this->givenAStockedMachine();

        $this->request('GET', '/api/machine');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            [
                'products' => [
                    ['selector' => 'JUICE', 'name' => 'Juice', 'price' => '1.00', 'count' => 10],
                    ['selector' => 'SODA', 'name' => 'Soda', 'price' => '1.50', 'count' => 10],
                    ['selector' => 'WATER', 'name' => 'Water', 'price' => '0.65', 'count' => 10],
                ],
                'changeReserve' => [
                    'coins' => [
                        ['denomination' => '0.05', 'count' => 10],
                        ['denomination' => '0.10', 'count' => 10],
                        ['denomination' => '0.25', 'count' => 10],
                    ],
                    'amount' => '4.00',
                ],
                'insertedCoins' => ['coins' => [], 'amount' => '0.00'],
                'acceptedCoins' => [
                    ['denomination' => '0.05', 'dispensableAsChange' => true],
                    ['denomination' => '0.10', 'dispensableAsChange' => true],
                    ['denomination' => '0.25', 'dispensableAsChange' => true],
                    ['denomination' => '1.00', 'dispensableAsChange' => false],
                ],
                'supportedCoins' => [
                    ['denomination' => '0.05', 'dispensableAsChange' => true, 'enabled' => true],
                    ['denomination' => '0.10', 'dispensableAsChange' => true, 'enabled' => true],
                    ['denomination' => '0.25', 'dispensableAsChange' => true, 'enabled' => true],
                    ['denomination' => '0.50', 'dispensableAsChange' => true, 'enabled' => false],
                    ['denomination' => '1.00', 'dispensableAsChange' => false, 'enabled' => true],
                    ['denomination' => '2.00', 'dispensableAsChange' => false, 'enabled' => false],
                ],
                'exactChangeOnly' => false,
                'outOfService' => false,
            ],
            $this->machineState(),
        );
    }

    /**
     * Two lists that answer two questions. `acceptedCoins` is what a customer
     * may put in right now; `supportedCoins` is what the acceptor can read at
     * all, and which of those this machine has switched on — the question a
     * technician asks, and the only one that can show a coin the machine is
     * not taking.
     */
    public function test_it_publishes_every_coin_the_acceptor_can_read(): void
    {
        $this->givenAStockedMachine();

        $this->request('GET', '/api/machine');

        $supported = $this->machineState()['supportedCoins'];
        self::assertIsArray($supported);
        self::assertSame(
            ['0.05', '0.10', '0.25', '0.50', '1.00', '2.00'],
            array_column($supported, 'denomination'),
        );
        self::assertSame(
            ['0.05' => true, '0.10' => true, '0.25' => true, '0.50' => false, '1.00' => true, '2.00' => false],
            array_column($supported, 'enabled', 'denomination'),
            'the four of the brief are on, and the two the acceptor learned to read are not',
        );
    }

    /**
     * The published lists have to agree with each other: whatever
     * `supportedCoins` marks enabled is exactly what `acceptedCoins` lists.
     * Two views of one fact, and a client that cross-checks them must never
     * find them disagreeing.
     */
    public function test_the_two_coin_lists_never_disagree(): void
    {
        $this->givenAStockedMachine();

        $this->request('GET', '/api/machine');

        $state = $this->machineState();
        self::assertIsArray($state['supportedCoins']);
        self::assertIsArray($state['acceptedCoins']);

        $enabled = array_column(
            array_filter(
                $state['supportedCoins'],
                static fn (mixed $coin): bool => \is_array($coin) && true === $coin['enabled'],
            ),
            'denomination',
        );

        self::assertSame(array_column($state['acceptedCoins'], 'denomination'), $enabled);
    }

    /**
     * The interpreted requirement, said out loud over the wire. The brief accepts
     * four coins and only ever returns three, and until now the only way for a
     * client to know that was to read the assumptions document and hardcode it.
     * A rule reimplemented on the far side of a network is a rule two systems
     * will eventually disagree about.
     */
    public function test_it_publishes_that_the_one_unit_coin_never_comes_back_as_change(): void
    {
        $this->givenAStockedMachine();

        $this->request('GET', '/api/machine');

        $acceptedCoins = $this->machineState()['acceptedCoins'];
        self::assertIsArray($acceptedCoins);

        self::assertSame(
            ['0.05' => true, '0.10' => true, '0.25' => true, '1.00' => false],
            array_column($acceptedCoins, 'dispensableAsChange', 'denomination'),
        );
    }

    /**
     * What the machine takes is not what the machine has. Serviced down to an
     * empty till, it still accepts every coin — the reserve is what it can pay
     * out with, and the two are different questions.
     */
    public function test_it_still_takes_every_coin_when_the_till_is_empty(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->withNoChange()
                ->build(),
        );

        $this->request('GET', '/api/machine');

        $acceptedCoins = $this->machineState()['acceptedCoins'];
        self::assertIsArray($acceptedCoins);
        self::assertCount(4, $acceptedCoins);
    }

    public function test_it_reports_the_coins_a_customer_has_already_inserted(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);

        $this->request('GET', '/api/machine');

        self::assertSame(
            [
                'coins' => [
                    ['denomination' => '0.10', 'count' => 1],
                    ['denomination' => '0.25', 'count' => 1],
                ],
                'amount' => '0.35',
            ],
            $this->machineState()['insertedCoins'],
        );
    }

    /**
     * The lamp on the front of the machine. It is part of the state precisely
     * so a client can warn before taking the customer's money, instead of
     * discovering it in a refused purchase.
     */
    public function test_it_lights_the_exact_change_lamp_when_the_till_cannot_pay_change(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->withNoChange()
                ->build(),
        );

        $this->request('GET', '/api/machine');

        self::assertTrue($this->machineState()['exactChangeOnly']);
    }

    public function test_it_answers_an_empty_catalogue_rather_than_omitting_it(): void
    {
        $this->store(VendingMachineBuilder::aMachine()->withId(self::machineId())->build());

        $this->request('GET', '/api/machine');

        self::assertSame([], $this->machineState()['products']);
        self::assertSame(['coins' => [], 'amount' => '0.00'], $this->machineState()['changeReserve']);
    }
}
