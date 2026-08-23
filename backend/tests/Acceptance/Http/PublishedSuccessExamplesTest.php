<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\OpenApi\OpenApiContract;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The success examples in docs/openapi.yaml, executed.
 *
 * PublishedExamplesTest closed this gap for the error side and stopped there,
 * which left the success examples in the worst position in the document: they
 * are the first thing an integrator copies, and nothing checked them at all —
 * the schema gate only validates the *responses the suite produces*, and the
 * example collector filters by problem+json. It was not theoretical: when the
 * state gained `supportedCoins` and `outOfService`, all five success examples
 * became invalid against their own schema, required properties missing, and
 * the whole suite stayed green. They were caught by hand.
 *
 * The bar chosen here is "a response this API really gives", not merely "a
 * body that satisfies the schema", and the choice was cheaper than it looks,
 * for two reasons worth recording. Schema validity comes free: every request
 * this suite makes is already validated against the contract on the way back
 * (ApiTestCase), so an example that equals a real response has passed the
 * schema by transitivity — a schema-only gate would have been a second,
 * weaker check on the same road. And the scenarios cost almost nothing,
 * because the examples were captured from real runs against the stocked
 * machine this suite already builds; writing the scenario is writing down
 * where the example came from. What the higher bar buys is the class of rot
 * schema validation cannot see: the plausible-but-false example — a
 * miscounted reserve, a wrong `dispensableAsChange` — that satisfies every
 * type in the schema while describing a machine this API would never answer
 * with.
 *
 * The list is derived, the scenarios are hand-written, and the `default` arm
 * refuses an example nobody wrote a scenario for — same construction as the
 * problem side, same reason: a gate that can be widened in silence is not a
 * gate.
 */
final class PublishedSuccessExamplesTest extends ApiTestCase
{
    #[DataProvider('everyPublishedSuccessExample')]
    public function test_the_published_example_is_a_response_this_api_really_gives(string $name): void
    {
        $published = OpenApiContract::successExampleOf($name);

        $this->provoke($name);

        self::assertResponseHeaderSame('content-type', 'application/json');

        // Normalized recursively where the problem side sorts one level,
        // because these bodies nest: a JSON object means the same thing in
        // any member order at every depth, and the YAML was written for a
        // human reader, not to mirror the serializer. Arrays keep their
        // order — a product list or a coin list in a different order IS a
        // different answer.
        self::assertSame(
            self::normalized($published),
            self::normalized($this->responseBody()),
            \sprintf('docs/openapi.yaml publishes an example named "%s" that this API does not produce.', $name),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyPublishedSuccessExample(): iterable
    {
        foreach (OpenApiContract::publishedSuccessExamples() as $name) {
            yield $name => [$name];
        }
    }

    private function provoke(string $name): void
    {
        match ($name) {
            'healthy' => $this->provokeHealthy(),
            'stocked' => $this->provokeStocked(),
            'oneEuroInserted' => $this->provokeOneEuroInserted(),
            'twoDimesBack' => $this->provokeTwoDimesBack(),
            'exactMoney' => $this->provokeExactMoney(),
            'oneProductOneCoin' => $this->provokeOneProductOneCoin(),
            default => self::fail(\sprintf(
                'docs/openapi.yaml publishes a success example named "%s" and this test knows no way to provoke it. Write the scenario below, or stop publishing the example.',
                $name,
            )),
        };
    }

    private function provokeHealthy(): void
    {
        $this->request('GET', '/api/health');
    }

    private function provokeStocked(): void
    {
        $this->givenAStockedMachine();

        $this->request('GET', '/api/machine');
    }

    private function provokeOneEuroInserted(): void
    {
        $this->givenAStockedMachine();

        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
    }

    private function provokeTwoDimesBack(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.10']);

        $this->request('POST', '/api/machine/coins/return');
    }

    private function provokeExactMoney(): void
    {
        $this->givenAStockedMachine();
        $this->request('POST', '/api/machine/coins', ['coin' => '1.00']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);
        $this->request('POST', '/api/machine/coins', ['coin' => '0.25']);

        $this->request('POST', '/api/machine/purchases', ['selector' => 'SODA']);
    }

    /**
     * No `acceptedCoins` in the payload on purpose: absent means "leave the
     * acceptor alone", and the published response shows the factory set —
     * this visit changed what the machine holds, not what it takes.
     */
    private function provokeOneProductOneCoin(): void
    {
        $this->store(
            VendingMachineBuilder::aStockedMachine()->withId(self::machineId())->build(),
        );

        $this->request('PUT', '/api/machine/service', [
            'products' => [
                ['selector' => 'TEA', 'name' => 'Iced Tea', 'price' => '0.80', 'count' => 4],
            ],
            'changeReserve' => [
                ['denomination' => '0.25', 'count' => 2],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array<array-key, mixed>
     */
    private static function normalized(array $document): array
    {
        foreach ($document as &$member) {
            if (\is_array($member)) {
                $member = self::normalized($member);
            }
        }
        unset($member);

        if (!array_is_list($document)) {
            ksort($document);
        }

        return $document;
    }
}
