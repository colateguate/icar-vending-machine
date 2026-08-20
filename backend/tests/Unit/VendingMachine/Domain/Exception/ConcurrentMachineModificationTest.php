<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Exception;

use App\VendingMachine\Domain\Exception\ConcurrentMachineModification;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Like MachineNotFound, this is a failure the domain names and infrastructure
 * raises, so it is tested here rather than only where it is thrown. The
 * adapter's test proves a second writer is stopped; this one proves the thing
 * that reaches the caller says what it should.
 */
final class ConcurrentMachineModificationTest extends TestCase
{
    public function test_it_names_the_machine_that_moved(): void
    {
        self::assertStringContainsString(
            'lobby-01',
            ConcurrentMachineModification::of('lobby-01')->getMessage(),
            'with a fleet, "someone changed a machine" is not an answer',
        );
    }

    public function test_it_is_a_failure_the_domain_anticipates(): void
    {
        self::assertInstanceOf(
            VendingMachineError::class,
            ConcurrentMachineModification::of('lobby-01'),
            'the edge maps it to 409; uncatalogued it would become an opaque 500',
        );
    }

    /**
     * The caller is told their read is stale, which is all they can act on.
     * Whoever reads the log needs the rest, and this is the only thing
     * carrying it there.
     */
    public function test_it_keeps_what_the_adapter_caught(): void
    {
        $cause = new RuntimeException('a version mismatch, in whatever words the adapter used');

        self::assertSame($cause, ConcurrentMachineModification::of('lobby-01', $cause)->getPrevious());
    }
}
