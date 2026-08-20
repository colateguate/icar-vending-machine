<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doubles\LosingTheRaceRepository;
use App\Tests\Support\OpenApi\OpenApiContract;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The examples in docs/openapi.yaml, executed.
 *
 * The document opens by promising that none of its examples was written from
 * memory — that each was captured from a real run of this suite. Nothing
 * enforced that promise, and it had already been broken: the example for
 * `concurrent_modification` published a title and a detail the API has never
 * returned, and could not have done otherwise, since it was the one failure no
 * test provoked. It was found by accident, while writing a test for something
 * else.
 *
 * What the other two gates check is narrower than it looks. Every response is
 * validated against the *schema*, and an invented example satisfies a schema
 * exactly as well as a captured one. The coverage test reads `status` and
 * `code` out of the examples and compares those against the catalog — two of
 * five members. `type`, `title` and `detail` were compared against nothing,
 * and before this class only two of the published details appeared anywhere
 * in the suite at all.
 *
 * So each case below puts the machine into the exact situation its example
 * describes and asserts that the whole document came back. The published
 * example stops being a plausible illustration and becomes the expected value.
 *
 * **The list is derived, the scenarios are hand-written**, and that split is
 * the point. Deriving the list means a sixteenth example cannot be published
 * without this class demanding a scenario for it — a gate that can be widened
 * in silence is not a gate. Deriving the expectations would assert that the
 * document agrees with itself, which is true of every document.
 */
final class PublishedExamplesTest extends ApiTestCase
{
    #[DataProvider('everyPublishedExample')]
    public function test_the_published_example_is_a_response_this_api_really_gives(string $name): void
    {
        $published = OpenApiContract::exampleOf($name);

        $this->provoke($name);

        self::assertResponseHeaderSame('content-type', 'application/problem+json');

        $answered = $this->responseBody();

        // Compared by key rather than in order: two JSON objects with the same
        // members mean the same thing whichever order they were written in, and
        // a test that failed over that would be about YAML rather than about
        // the contract. Everything else stays strict, types included.
        ksort($published);
        ksort($answered);

        self::assertSame($published, $answered, \sprintf(
            'docs/openapi.yaml publishes an example named "%s" that this API does not produce.',
            $name,
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyPublishedExample(): iterable
    {
        foreach (OpenApiContract::publishedProblemExamples() as $name) {
            yield $name => [$name];
        }
    }

    /**
     * The `default` arm is the guardrail: an example nobody wrote a scenario
     * for fails here, naming itself, instead of quietly not being checked.
     */
    private function provoke(string $name): void
    {
        match ($name) {
            'bodyThatIsNotJson' => $this->provokeBodyThatIsNotJson(),
            'concurrentModification' => $this->provokeConcurrentModification(),
            'coinThatIsNotAnAmount' => $this->provokeCoinThatIsNotAnAmount(),
            'coinThatIsNotAString' => $this->provokeCoinThatIsNotAString(),
            'exactChangeRequired' => $this->provokeExactChangeRequired(),
            'insufficientFunds' => $this->provokeInsufficientFunds(),
            'missingProducts' => $this->provokeMissingProducts(),
            'missingSelector' => $this->provokeMissingSelector(),
            'notProvisioned' => $this->provokeNotProvisioned(),
            'priceThatIsNotAnAmount' => $this->provokePriceThatIsNotAnAmount(),
            'productOutOfStock' => $this->provokeProductOutOfStock(),
            'selectorInLowercase' => $this->provokeSelectorInLowercase(),
            'selectorWithASpace' => $this->provokeSelectorWithASpace(),
            'unknownProduct' => $this->provokeUnknownProduct(),
            'unsupportedCoin' => $this->provokeUnsupportedCoin(),
            default => self::fail(\sprintf(
                'docs/openapi.yaml publishes an example named "%s" and this test knows no way to provoke it. Write the scenario below, or stop publishing the example.',
                $name,
            )),
        };
    }

    private function provokeBodyThatIsNotJson(): void
    {
        $this->givenAStockedMachine();

        $this->requestWithRawBody('POST', '/api/machine/coins', '{"coin": ');
    }

    /**
     * The only scenario that needs the port replaced, and the swap has to
     * happen before anything asks the container for the repository — which is
     * why it is the first thing this method does. See
     * ConcurrentModificationEndpointTest for the whole of that argument.
     */
    private function provokeConcurrentModification(): void
    {
        self::getContainer()->set(
            VendingMachineRepository::class,
            new LosingTheRaceRepository(
                VendingMachineBuilder::aStockedMachine()->withId(self::machineId())->build(),
            ),
        );

        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
    }

    private function provokeCoinThatIsNotAnAmount(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => 'a lot']);
    }

    private function provokeCoinThatIsNotAString(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => 25]);
    }

    private function provokeExactChangeRequired(): void
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
    }

    private function provokeInsufficientFunds(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'WATER']);
    }

    private function provokeMissingProducts(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', ['changeReserve' => []]);
    }

    /**
     * A raw body rather than an empty array, because PHP encodes [] as a JSON
     * array and the endpoint would refuse it as "not an object" — a 400 about
     * the bytes, which is a different refusal from the 422 this documents.
     */
    private function provokeMissingSelector(): void
    {
        $this->givenAStockedMachine();

        $this->requestWithRawBody('POST', '/api/machine/purchases', '{}');
    }

    /**
     * No machine is stored, which is the situation: nothing was named wrong,
     * this deployment was simply never provisioned.
     */
    private function provokeNotProvisioned(): void
    {
        $this->request('GET', '/api/machine');
    }

    private function provokePriceThatIsNotAnAmount(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [
                ['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => 'free', 'count' => 4],
            ],
            'changeReserve' => [],
        ]);
    }

    private function provokeProductOutOfStock(): void
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
    }

    private function provokeSelectorInLowercase(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/purchases', ['selector' => 'water']);
    }

    private function provokeSelectorWithASpace(): void
    {
        $this->givenAStockedMachine();

        $this->request('PUT', '/api/machine/service', [
            'products' => [
                ['selector' => 'iced tea', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4],
            ],
            'changeReserve' => [],
        ]);
    }

    private function provokeUnknownProduct(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::machineId())
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->build(),
        );

        $this->request('POST', '/api/machine/purchases', ['selector' => 'SODA']);
    }

    private function provokeUnsupportedCoin(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '0.02']);
    }
}
