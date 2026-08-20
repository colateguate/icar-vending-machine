<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Http\Error;

use App\Tests\Support\OpenApi\OpenApiContract;
use App\Tests\Support\Reflection\FailureClasses;
use App\VendingMachine\Delivery\Http\Error\ErrorCatalog;
use PHPUnit\Framework\TestCase;

/**
 * The error catalog and docs/openapi.yaml are two statements of the same
 * contract, written in two languages for two audiences: one the delivery
 * layer reads at runtime, one a person reads before writing a client. Two
 * statements of one thing is one thing too many unless something keeps them
 * equal, and these two tests are that something.
 *
 * The acceptance suite already validates every response it produces against
 * the spec, which covers a great deal — but not this. It can only check the
 * failures it happens to provoke, and concurrent_modification is provoked by
 * two connections racing, which is an integration-level setup. Without the
 * test below, that failure could go undocumented forever while the whole
 * suite stayed green.
 *
 * Neither test boots anything. The question is whether two declarations agree,
 * and that is answered by reading them.
 */
final class OpenApiErrorCoverageTest extends TestCase
{
    /**
     * A client integrating against the published contract should never meet
     * an error the contract does not mention. Adding an exception and
     * cataloguing it are two steps people remember; documenting it is the
     * third, so the suite remembers instead.
     */
    public function test_every_catalogued_failure_is_documented_in_the_published_contract(): void
    {
        $documented = self::documentedFailures();

        self::assertNotEmpty($documented, 'the contract documents no failures at all — this test is reading the wrong file');

        foreach (FailureClasses::catalogued() as $failure) {
            $problem = ErrorCatalog::of($failure);

            self::assertContains(
                self::pair($problem->status, $problem->code),
                $documented,
                self::explain(\sprintf(
                    '%s is answered as %d %s, and docs/openapi.yaml documents no such failure.',
                    $failure,
                    $problem->status,
                    $problem->code,
                ), $documented),
            );
        }
    }

    /**
     * And the other direction, which is the one that rots quietly: an error
     * that stops existing leaves its paragraph behind, and the document keeps
     * promising a failure the code can no longer produce. Nobody notices,
     * because nothing fails when a promise is merely never kept.
     */
    public function test_the_published_contract_documents_no_failure_the_catalog_cannot_produce(): void
    {
        $catalogued = array_map(
            static function (string $failure): string {
                $problem = ErrorCatalog::of($failure);

                return self::pair($problem->status, $problem->code);
            },
            FailureClasses::catalogued(),
        );

        self::assertNotEmpty($catalogued, 'nothing is catalogued at all — this test is looking in the wrong place');

        foreach (self::documentedFailures() as $documented) {
            self::assertContains(
                $documented,
                $catalogued,
                self::explain(\sprintf(
                    'docs/openapi.yaml documents %s, and nothing in the catalog answers that.',
                    $documented,
                ), $catalogued),
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function documentedFailures(): array
    {
        return array_map(
            static fn (array $problem): string => self::pair($problem['status'], $problem['code']),
            OpenApiContract::documentedProblems(),
        );
    }

    private static function pair(int $status, string $code): string
    {
        return $status.' '.$code;
    }

    /**
     * @param list<string> $known
     */
    private static function explain(string $headline, array $known): string
    {
        return \sprintf("%s\n\nKnown on the other side:\n  %s\n", $headline, implode("\n  ", $known));
    }
}
