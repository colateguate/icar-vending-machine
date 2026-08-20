<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\LosingTheRaceRepository;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a client sees when its write lost a race.
 *
 * Of the eleven failures in the catalog this was the only one the acceptance
 * suite never produced, and the parts were each proven separately: the adapter
 * raises it under a real race, the catalog maps it to 409, the published
 * contract documents it. What nobody had ever run was the join — this
 * exception travelling through the subscriber and coming out as a problem
 * document — and a chain proven link by link is not a proven chain.
 *
 * **Why the machine arrives through a stand-in port.** The question this level
 * answers is "what does the caller get", and that question does not need the
 * race to be real. Whether the adapter notices a genuine concurrent write is a
 * different question, asked where it lives, against two live connections: see
 * ConcurrentPurchaseTest in the integration suite. Racing two connections
 * inside a kernel that holds its own connection open would test that same
 * thing a second time, and be far more fragile doing it.
 *
 * **Why the double serves the machine too**, rather than the usual
 * givenAStockedMachine(). Replacing a service in the test container throws
 * "service is already initialized" once anything has asked for it, and
 * givenAStockedMachine() asks for the repository in order to store through it.
 * So the swap has to happen before the container is touched at all, which
 * leaves the double as the only thing left that can hold the machine. Anyone
 * simplifying this back to a stored machine will meet that error, and this
 * paragraph is here so they know it is the design and not an accident.
 */
final class ConcurrentModificationEndpointTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->set(
            VendingMachineRepository::class,
            new LosingTheRaceRepository(
                VendingMachineBuilder::aStockedMachine()
                    ->withId(self::machineId())
                    // A coin is already in, so that a purchase gets as far as
                    // the write instead of being turned away for insufficient
                    // funds. What is under test is the write losing, not the
                    // checks in front of it.
                    ->withInsertedCoins(CoinDenomination::ONE_UNIT)
                    ->build(),
            ),
        );
    }

    /**
     * Nothing the caller sent was wrong, which is why this is a 409 rather
     * than a 422, and why the detail says the machine moved instead of
     * blaming the request.
     */
    public function test_a_write_that_lost_the_race_carries_the_full_problem_document(): void
    {
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(
            [
                'type' => '/problems/concurrent-modification',
                'title' => 'The machine changed underneath you',
                'status' => 409,
                'detail' => 'The machine "lobby-01" was changed by someone else while this request was being handled.',
                'code' => 'concurrent_modification',
            ],
            $this->responseBody(),
        );
    }

    /**
     * Every endpoint that writes can lose the same race, and a client that
     * branches on `code` should not have to learn four spellings of it.
     *
     * One test per endpoint rather than a loop inside one test, because the
     * double hands out the same aggregate every time it is asked and never
     * writes it back: a loop would carry each endpoint's mutation into the
     * next one, and returning the coins would leave the purchase with nothing
     * to spend. A separate test gets a separate kernel and a separate machine.
     *
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('everyEndpointThatWrites')]
    public function test_every_endpoint_that_writes_reports_it_the_same_way(string $method, string $uri, ?array $body): void
    {
        $this->request($method, $uri, $body);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('concurrent_modification', $this->responseBody()['code']);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>|null}>
     */
    public static function everyEndpointThatWrites(): iterable
    {
        yield 'inserting a coin' => ['POST', '/api/machine/coins', ['coin' => '0.25']];
        yield 'returning the coins' => ['POST', '/api/machine/coins/return', null];
        yield 'buying a product' => ['POST', '/api/machine/purchases', ['selector' => 'WATER']];
        yield 'servicing the machine' => ['PUT', '/api/machine/service', ['products' => [], 'changeReserve' => []]];
    }

    /**
     * And the control: reading is not writing, so the query still answers.
     * Without it, a double that broke every request would be indistinguishable
     * from one that breaks only the writes — and the four assertions above
     * would pass either way.
     */
    public function test_reading_the_machine_is_unaffected(): void
    {
        $this->request('GET', '/api/machine');

        self::assertResponseIsSuccessful();
    }
}
