<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Shared setup for the tests that exercise the API the way a client does.
 *
 * Two things every test here needs. First, the kernel must survive between
 * requests: until Doctrine arrives the machine lives in a service, and a
 * rebooted kernel would forget it between "insert a coin" and "buy the soda" —
 * the very sequence the brief's examples are made of. Second, a machine has to
 * exist before anything can be asked of it, and it is provisioned through the
 * repository rather than through the API because PUT /api/machine/service
 * services a machine that is already there; it does not create one.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
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
    }

    protected function requestWithRawBody(string $method, string $uri, string $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: $body);
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
