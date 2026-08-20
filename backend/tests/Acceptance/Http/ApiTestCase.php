<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\Tests\Support\Doctrine\Schema;
use App\Tests\Support\OpenApi\OpenApiContract;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Shared setup for the tests that exercise the API the way a client does.
 *
 * Three things every test here needs.
 *
 * A database: the tests run against real SQLite, held in memory, with the
 * tables built from the real mapping.
 *
 * A kernel that survives between requests. An in-memory database lives for
 * exactly as long as the connection that opened it, so a rebooted kernel would
 * open a new one and find nothing — losing the machine between "insert a coin"
 * and "buy the soda", which is the sequence the brief's examples are made of.
 * It also means no cleanup: the tables die with the test.
 *
 * And a machine, put there through the repository rather than through the API,
 * because PUT /api/machine/service services a machine that already exists; it
 * does not create one. Creating is what app:machine:provision is for.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        Schema::createForContainer(self::getContainer());
    }

    /**
     * Water 0.65, Juice 1.00, Soda 1.50 and a full till — the catalogue of the
     * brief.
     */
    protected function givenAStockedMachine(): void
    {
        $this->store(VendingMachineBuilder::aStockedMachine()->withId(self::machineId())->build());
    }

    protected function store(VendingMachine $machine): void
    {
        $repository = self::getContainer()->get(VendingMachineRepository::class);
        self::assertInstanceOf(VendingMachineRepository::class, $repository);

        $repository->save($machine);
    }

    protected static function machineId(): string
    {
        return 'lobby-01';
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function request(string $method, string $uri, ?array $body = null): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null === $body ? '' : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseMatchesTheContract($method, $uri);
    }

    protected function requestWithRawBody(string $method, string $uri, string $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: $body);

        $this->assertResponseMatchesTheContract($method, $uri);
    }

    /**
     * A request the published contract does not describe, and cannot.
     * OpenAPI documents the paths an API declares, so "a path this API
     * never declared" has nowhere to live in the document. The refusals
     * that answer such a request — 404 for an unknown route, 405 for a
     * method a known route does not take — come from the router rather
     * than from the domain, and the catalog leaves them to it.
     *
     * It is a named method rather than a flag, and rather than silently
     * skipping validation whenever the spec has no such path, because
     * that silence is the failure mode worth designing against: a typo
     * in a URI would stop validating the very response the test exists
     * to check, and the suite would stay green while checking nothing.
     */
    protected function requestOutsideTheContract(string $method, string $uri): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json']);
    }

    private function assertResponseMatchesTheContract(string $method, string $uri): void
    {
        OpenApiContract::assertResponseMatches($method, $uri, $this->client->getResponse());
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function responseBody(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function machineState(): array
    {
        return $this->responseArray('machine');
    }

    /**
     * One value out of the response document, addressed by path:
     * responseValue('machine', 'insertedCoins', 'amount').
     *
     * A decoded body is mixed all the way down as far as the analyser is
     * concerned, and chaining offsets on mixed is exactly what it refuses to
     * let you do. Walking the path with an assertion at each step buys
     * something back for the ceremony: a key that is not there fails naming
     * itself, instead of turning into a null that fails an equality three
     * lines later.
     */
    protected function responseValue(string|int ...$path): mixed
    {
        $value = $this->responseBody();

        foreach ($path as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value, \sprintf('the response has no "%s" at this level', $key));
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function responseArray(string|int ...$path): array
    {
        $value = $this->responseValue(...$path);
        self::assertIsArray($value);

        return $value;
    }
}
