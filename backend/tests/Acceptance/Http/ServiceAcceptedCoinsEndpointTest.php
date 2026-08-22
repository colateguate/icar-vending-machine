<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The half of a service visit that configures the coin acceptor.
 *
 * It travels as its own field rather than as a flag on the till rows, because
 * the two say different things: the till says how many coins are in there, the
 * acceptor says which denominations the machine takes at all. A coin can be in
 * one and not the other in both directions — stranded money the machine no
 * longer takes, and a denomination it takes with none of it left.
 */
final class ServiceAcceptedCoinsEndpointTest extends ApiTestCase
{
    public function test_a_visit_can_switch_a_denomination_on(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [['denomination' => '0.50', 'count' => 4]],
            'acceptedCoins' => ['0.25', '0.50'],
        ]);

        self::assertResponseStatusCodeSame(200);
        $accepted = $this->machineState()['acceptedCoins'];
        self::assertIsArray($accepted);
        self::assertSame(['0.25', '0.50'], array_column($accepted, 'denomination'));
    }

    /**
     * The coin the machine was just told to take is a coin it will now let in.
     * Asserted through the slot rather than through the state, because that is
     * the promise a customer standing in front of it cares about.
     */
    public function test_a_denomination_switched_on_is_taken_at_the_slot(): void
    {
        $this->givenAStockedMachine();
        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => ['0.50'],
        ]);

        $this->request('POST', '/api/machine/coins', ['coin' => '0.50']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('0.50', $this->responseValue('machine', 'insertedCoins', 'amount'));
    }

    /**
     * A visit that says nothing about coins leaves the acceptor alone. An
     * absent field and an empty list mean different things here, which is the
     * distinction this test exists to pin.
     */
    public function test_a_visit_that_omits_the_field_leaves_the_acceptor_alone(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [['denomination' => '0.05', 'count' => 2]],
        ]);

        self::assertResponseStatusCodeSame(200);
        $accepted = $this->machineState()['acceptedCoins'];
        self::assertIsArray($accepted);
        self::assertSame(['0.05', '0.10', '0.25', '1.00'], array_column($accepted, 'denomination'));
        self::assertFalse($this->machineState()['outOfService']);
    }

    /**
     * The state the epic exists to make reachable: a technician can switch the
     * acceptor off entirely, and the machine says so rather than leaving a
     * customer to discover it coin by coin.
     */
    public function test_a_visit_can_take_the_machine_out_of_service(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => [],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->machineState()['acceptedCoins']);
        self::assertTrue($this->machineState()['outOfService']);
    }

    public function test_a_machine_out_of_service_takes_no_coin_at_all(): void
    {
        $this->givenAStockedMachine();
        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => [],
        ]);

        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('coin_not_accepted', $this->responseBody()['code']);
    }

    /**
     * Money already inside when its denomination is switched off. The visit has
     * to be able to say it is there, and the machine has to stop counting it as
     * change it can pay.
     */
    public function test_a_visit_can_declare_coins_the_machine_no_longer_takes(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [['denomination' => '0.50', 'count' => 4]],
            'acceptedCoins' => ['1.00'],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('2.00', $this->responseValue('machine', 'changeReserve', 'amount'));
        self::assertTrue(
            $this->machineState()['exactChangeOnly'],
            'a till of coins it may not hand back cannot pay change',
        );
    }

    /**
     * The whole feature, end to end and through the slot: switch 0.50 on, load
     * the till with it, and let a customer overpay by exactly that.
     */
    public function test_a_denomination_switched_on_comes_back_as_change(): void
    {
        $this->givenAStockedMachine();
        $this->request('PUT', '/api/machine/service', [
            'products' => [['selector' => 'WATER', 'name' => 'Water', 'price' => '0.50', 'count' => 1]],
            'changeReserve' => [['denomination' => '0.50', 'count' => 2]],
            'acceptedCoins' => ['0.50', '1.00'],
        ]);

        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(200);
        $change = $this->responseValue('dispensed', 'change');
        self::assertIsArray($change);
        self::assertSame([['denomination' => '0.50', 'count' => 1]], $change['coins']);
    }

    /**
     * @param list<mixed> $acceptedCoins
     */
    #[DataProvider('payloadsTheAcceptorRefuses')]
    public function test_it_refuses_a_coin_list_it_cannot_read(array $acceptedCoins, string $code): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => $acceptedCoins,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($code, $this->responseBody()['code']);
    }

    /**
     * @return iterable<string, array{list<mixed>, string}>
     */
    public static function payloadsTheAcceptorRefuses(): iterable
    {
        yield 'a denomination as a JSON number' => [[0.25], 'invalid_request_payload'];
        yield 'a denomination that is not an amount' => [['two bits'], 'invalid_money_amount'];
        yield 'a coin no machine of this model reads' => [['0.02'], 'unsupported_coin'];
    }

    /**
     * @param mixed $acceptedCoins what the client sent where a list belongs
     */
    #[DataProvider('coinListsThatAreNotLists')]
    public function test_it_refuses_a_coin_list_that_is_not_a_list(mixed $acceptedCoins): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => $acceptedCoins,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_request_payload', $this->responseBody()['code']);
        self::assertSame('acceptedCoins', $this->responseBody()['field']);
    }

    /**
     * Null is the interesting one, and it is refused rather than read as
     * silence. Saying nothing about the acceptor is done by leaving the field
     * out; sending it as null is a client that meant something and did not say
     * what, and guessing which of "leave it alone" or "take nothing" they meant
     * is the guess that empties a machine.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function coinListsThatAreNotLists(): iterable
    {
        yield 'a bare denomination' => ['0.25'];
        yield 'null' => [null];
        yield 'an object keyed by index' => [(object) ['0' => '0.25']];
    }

    /**
     * Naming the same coin twice is not the same mistake as declaring the same
     * till row twice: a set has no count to be ambiguous about, so it is read
     * rather than refused. The contrast with the reserve is deliberate.
     */
    public function test_naming_a_denomination_twice_takes_it_once(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [],
            'changeReserve' => [],
            'acceptedCoins' => ['0.25', '0.25'],
        ]);

        self::assertResponseStatusCodeSame(200);
        $accepted = $this->machineState()['acceptedCoins'];
        self::assertIsArray($accepted);
        self::assertSame(['0.25'], array_column($accepted, 'denomination'));
    }
}
