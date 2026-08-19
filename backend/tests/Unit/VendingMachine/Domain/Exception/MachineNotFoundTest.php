<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Exception;

use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use PHPUnit\Framework\TestCase;

/**
 * A domain exception whose only thrower lives in infrastructure — the
 * repository port declares it, adapters raise it. That is why it gets a test
 * of its own here rather than riding along with the class that throws it: the
 * domain's vocabulary is tested in the domain's own suite.
 */
final class MachineNotFoundTest extends TestCase
{
    public function test_it_names_the_machine_that_is_missing(): void
    {
        self::assertStringContainsString(
            'lobby-01',
            MachineNotFound::withId('lobby-01')->getMessage(),
            'an operator reading this needs to know which machine was asked for',
        );
    }

    public function test_it_is_a_failure_the_domain_anticipates(): void
    {
        self::assertInstanceOf(
            VendingMachineError::class,
            MachineNotFound::withId('lobby-01'),
            'the edge maps it to 503; an unanticipated failure would become an opaque 500',
        );
    }
}
