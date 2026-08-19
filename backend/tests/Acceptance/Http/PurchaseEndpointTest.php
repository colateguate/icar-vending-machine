<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;

/**
 * POST /api/machine/purchases — the endpoint where every refusal of the domain
 * has to arrive as the right status.
 *
 * Plural on purpose: a purchase is a thing that happened, not a procedure
 * being called, which leaves room for a purchase read model later without the
 * URL having to change.
 */
final class PurchaseEndpointTest extends ApiTestCase
{
    public function test_it_dispenses_the_product_and_the_change(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('0.35', $this->responseValue('dispensed', 'change', 'amount'));
    }

    public function test_it_answers_with_the_machine_left_behind_by_the_sale(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        $state = $this->machineState();
        self::assertSame(9, $this->responseValue('machine', 'products', 2, 'count'), 'WATER is the third selector alphabetically');
        self::assertSame(['coins' => [], 'amount' => '0.00'], $state['insertedCoins']);
        self::assertSame('4.65', $this->responseValue('machine', 'changeReserve', 'amount'), 'the 1.00 stays, 0.35 went out');
    }

    /**
     * You named something that does not exist here. Not a malformed request —
     * SODA is a perfectly well-formed selector — so 404 rather than 422.
     */
    public function test_it_answers_404_for_a_product_the_machine_does_not_stock(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->build(),
        );
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'SODA']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('unknown_product', $this->responseBody()['code']);
    }

    /**
     * "water" is not a selector at all — selectors are uppercase identifiers.
     * The value itself is wrong, which is the 422 case.
     */
    public function test_it_answers_422_for_a_selector_that_is_not_one(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/purchases', ['selector' => 'water']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_product_selector', $this->responseBody()['code']);
    }

    public function test_it_answers_409_when_the_slot_is_empty(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 0)
                ->withChangeReserve([5 => 10, 10 => 10, 25 => 10])
                ->build(),
        );
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('product_out_of_stock', $this->responseBody()['code']);
    }

    public function test_it_answers_409_and_says_how_much_is_missing(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('insufficient_funds', $this->responseBody()['code']);
        self::assertSame('0.40', $this->responseBody()['missingAmount']);
    }

    /**
     * The refusal the brief never mentions and the machine has to have an
     * answer for. The customer is told what could not be paid, and the coins
     * stay where they are so RETURN-COIN is still a way out.
     */
    public function test_it_answers_409_when_the_change_cannot_be_composed(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->withNoChange()
                ->build(),
        );
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('exact_change_required', $this->responseBody()['code']);
        self::assertSame('0.35', $this->responseBody()['changeDue']);
    }

    public function test_a_refused_sale_leaves_the_coins_in_the_escrow(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->withNoChange()
                ->build(),
        );
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);

        $this->request('GET', '/api/machine');

        self::assertSame('1.00', $this->responseValue('machine', 'insertedCoins', 'amount'));
        self::assertSame(5, $this->responseValue('machine', 'products', 0, 'count'));
    }

    public function test_it_rejects_a_body_without_a_selector(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/purchases', ['product' => 'WATER']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_request_payload', $this->responseBody()['code']);
    }
}
