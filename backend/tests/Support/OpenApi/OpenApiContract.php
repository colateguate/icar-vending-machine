<?php

declare(strict_types=1);

namespace App\Tests\Support\OpenApi;

use cebe\openapi\spec\Example;
use cebe\openapi\spec\MediaType;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The published contract, used as an assertion.
 *
 * docs/openapi.yaml is the only description of this API that someone who has
 * not read the code can act on, which makes it exactly the kind of document
 * that rots: it is written once, believed forever, and never executed. This
 * class is what executes it. Every response the acceptance suite produces is
 * checked against the spec, so the spec cannot drift from the API without the
 * build going red.
 *
 * It reads the same file a client imports into Postman. Not a copy of it.
 */
final class OpenApiContract
{
    private const PROBLEM_MEDIA_TYPE = 'application/problem+json';

    private static ?ResponseValidator $validator = null;

    /**
     * @var array<string, array{status: int, code: string, value: array<array-key, mixed>}>|null
     */
    private static ?array $examples = null;

    public static function specPath(): string
    {
        return \dirname(__DIR__, 4).'/docs/openapi.yaml';
    }

    /**
     * Every failure the contract documents, as the pair a client branches on.
     *
     * Deduplicated: several examples can answer with the same status and code
     * and differ only in which field they blame, and a client branching on
     * `code` sees one failure there rather than three.
     *
     * @return list<array{status: int, code: string}>
     */
    public static function documentedProblems(): array
    {
        $problems = [];

        foreach (self::documentedExamples() as $example) {
            $problems[$example['status'].' '.$example['code']] = [
                'status' => $example['status'],
                'code' => $example['code'],
            ];
        }

        ksort($problems);

        return array_values($problems);
    }

    /**
     * The name of every distinct problem+json example the document publishes.
     *
     * @return list<string>
     */
    public static function publishedProblemExamples(): array
    {
        return array_keys(self::documentedExamples());
    }

    /**
     * The whole of one published example, addressed by the name it is
     * published under, so a test can compare it against a real response
     * instead of against two of its five members.
     *
     * @return array<array-key, mixed>
     */
    public static function exampleOf(string $name): array
    {
        $examples = self::documentedExamples();

        Assert::assertArrayHasKey($name, $examples, \sprintf(
            'docs/openapi.yaml publishes no problem+json example named "%s". It publishes: %s.',
            $name,
            implode(', ', array_keys($examples)) ?: 'none at all',
        ));

        return $examples[$name]['value'];
    }

    /**
     * Every distinct problem+json example the document publishes, whole,
     * keyed by its published name.
     *
     * Read out of the examples rather than out of the response declarations,
     * because a status with a schema and no example says "something can go
     * wrong here" without saying what — and "what" is the whole reason a
     * caller reads an error contract.
     *
     * **Keyed by name and not by status and code**, which was the first
     * version and was wrong in a way that hid work: four codes are documented
     * by more than one example — a missing field is a different example on
     * each endpoint — so keying by the pair collapsed fifteen examples into
     * eleven and left four of them checked by nothing at all. A gate that
     * quietly covers less than it claims is the failure this file exists to
     * prevent.
     *
     * One example may legitimately be published under one name by several
     * operations, and those collapse into one. What must never happen is one
     * name carrying two different examples, because then the name identifies
     * neither — so that fails here instead of becoming a silent gap again.
     *
     * Resolved once: the walk parses the whole document and asserts as it
     * goes, and repeating it per call would bury the suite's assertion count
     * under hundreds of comparisons of the file with itself.
     *
     * @return array<string, array{status: int, code: string, value: array<array-key, mixed>}>
     */
    private static function documentedExamples(): array
    {
        if (null !== self::$examples) {
            return self::$examples;
        }

        $examples = [];

        foreach (self::validator()->getSchema()->paths as $pathItem) {
            foreach ($pathItem->getOperations() as $operation) {
                foreach ($operation->responses ?? [] as $status => $response) {
                    // Responses are keyed by status code, but as an OpenAPI
                    // key that may also be a range ("4XX") or the literal
                    // "default" — neither of which this contract uses, and
                    // both of which would be a status nobody can branch on.
                    if (!is_numeric($status)) {
                        continue;
                    }

                    $mediaType = $response->content[self::PROBLEM_MEDIA_TYPE] ?? null;

                    if (!$mediaType instanceof MediaType) {
                        continue;
                    }

                    foreach (self::examplesOf($mediaType) as $name => $value) {
                        $code = $value['code'] ?? null;

                        if (!\is_string($code)) {
                            continue;
                        }

                        $entry = ['status' => (int) $status, 'code' => $code, 'value' => $value];

                        Assert::assertSame(
                            $examples[$name] ?? $entry,
                            $entry,
                            \sprintf('docs/openapi.yaml publishes two different examples under the name "%s", so the name identifies neither.', $name),
                        );

                        $examples[$name] = $entry;
                    }
                }
            }
        }

        ksort($examples);

        return self::$examples = $examples;
    }

    /**
     * The examples one media type carries, by name.
     *
     * OpenAPI lets a media type carry a single nameless `example` as well as a
     * named set, and a problem example without a name is refused here rather
     * than skipped: this gate addresses examples by name, so an anonymous one
     * would be checked by nothing while looking exactly like one that is.
     *
     * @return array<string, array<array-key, mixed>>
     */
    private static function examplesOf(MediaType $mediaType): array
    {
        Assert::assertNull(
            $mediaType->example,
            'a problem+json example in docs/openapi.yaml has no name. Publish it under "examples:" with one, so that something can be said to check it.',
        );

        $values = [];

        foreach ($mediaType->examples as $name => $example) {
            if (\is_string($name) && $example instanceof Example && \is_array($example->value)) {
                $values[$name] = $example->value;
            }
        }

        return $values;
    }

    /**
     * Fails the test unless the response matches what the spec promises for
     * this operation: the status must be documented, the content type must be
     * one the operation declares, and the body must satisfy its schema.
     */
    public static function assertResponseMatches(string $method, string $uri, Response $response): void
    {
        $mismatch = self::mismatchOf($method, $uri, $response);

        // An assertion rather than a bare fail(), so that every checked
        // response is counted as one. A gate that registers nothing when it
        // passes leaves no trace of having run, and "the suite is green"
        // stops being evidence that anything was compared.
        //
        // assertTrue on a comparison rather than assertNull on the exception,
        // because PHPUnit appends its own description of the failure to ours:
        // assertNull dumps the whole exception object underneath a report that
        // already says everything worth saying, and the dump is what the
        // reader's eye lands on. "Failed asserting that false is true" is six
        // words of noise instead of ten lines of it.
        Assert::assertTrue(
            null === $mismatch,
            null === $mismatch ? '' : self::report($method, $uri, $response, $mismatch),
        );
    }

    private static function mismatchOf(string $method, string $uri, Response $response): ?Throwable
    {
        $address = new OperationAddress(self::pathOf($uri), strtolower($method));

        $psrResponse = new PsrResponse(
            $response->getStatusCode(),
            $response->headers->all(),
            (string) $response->getContent(),
        );

        try {
            self::validator()->validate($address, $psrResponse);

            return null;
        } catch (Throwable $mismatch) {
            return $mismatch;
        }
    }

    /**
     * A failure here is read by someone who was testing something else and did
     * not expect the contract to be involved at all, so the message carries
     * everything needed to judge it without opening the spec: what was asked,
     * what came back, and the chain of reasons the validator gave — which is
     * where the useful sentence lives (the outer exception only says "body
     * does not match schema").
     */
    private static function report(string $method, string $uri, Response $response, Throwable $failure): string
    {
        // NoResponseCode extends NoOperation extends NoPath, so the most
        // specific goes first — and it is worth telling apart, because "this
        // status is not documented" is the everyday case (someone taught the
        // API a new refusal) while "this path is not documented" means the
        // request was never meant to be under the contract at all.
        $headline = match (true) {
            $failure instanceof NoResponseCode => \sprintf(
                'the contract does not document status %d for this operation. Add it to docs/openapi.yaml.',
                $response->getStatusCode(),
            ),
            $failure instanceof NoPath => 'the contract declares no such operation. Add it to docs/openapi.yaml, '
                .'or use requestOutsideTheContract() if this request is deliberately outside it.',
            default => 'the response does not match what docs/openapi.yaml promises.',
        };

        $reasons = [];

        for ($cause = $failure; null !== $cause; $cause = $cause->getPrevious()) {
            $reasons[] = '  - '.trim(explode("\n", $cause->getMessage())[0]);
        }

        return \sprintf(
            "OpenAPI contract: %s\n\n  %s %s -> %d %s\n  body: %s\n\n%s\n",
            $headline,
            strtoupper($method),
            $uri,
            $response->getStatusCode(),
            (string) $response->headers->get('Content-Type'),
            (string) $response->getContent(),
            implode("\n", $reasons),
        );
    }

    /**
     * Every route in this API is a fixed path — the machine is a singleton, so
     * nothing is templated — which is why the URI can be addressed verbatim
     * instead of being resolved back to a route pattern. A query string is
     * dropped because OpenAPI keys operations by path alone.
     */
    private static function pathOf(string $uri): string
    {
        return parse_url($uri, \PHP_URL_PATH) ?: $uri;
    }

    private static function validator(): ResponseValidator
    {
        return self::$validator ??= (new ValidatorBuilder())
            ->fromYamlFile(self::specPath())
            ->getResponseValidator();
    }
}
