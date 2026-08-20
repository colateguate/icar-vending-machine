<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Http;

use App\Tests\Support\OpenApi\OpenApiContract;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthCheckTest extends WebTestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame('{"status":"ok"}', $client->getResponse()->getContent());
        OpenApiContract::assertResponseMatches('GET', '/api/health', $client->getResponse());
    }

    /**
     * Deliberately not checked against docs/openapi.yaml: OpenAPI keys
     * everything by declared path, so a route this API never declared has
     * nowhere to live in the document. This is the smoke-test version —
     * ProblemDetailsContractTest::test_an_unknown_route_is_a_problem_document_too
     * is the one that asserts the envelope, and it says the same thing out
     * loud through requestOutsideTheContract().
     */
    public function test_unknown_route_returns_404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }
}
