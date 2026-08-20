<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\TestCase;

/**
 * The one field on this aggregate that exists for the sake of storage.
 *
 * It is not business logic and it never will be: no rule of a vending machine
 * mentions a version number. It is here because two people can press the same
 * button at the same instant, and the cheapest honest answer to that is a
 * counter the database checks on every write (ADR-0011).
 *
 * The domain still owns it rather than hiding it in an adapter, and that is a
 * deliberate trade written down in the ADR: Doctrine can only guard a field it
 * can map, and the alternative — a parallel persistence model — buys purity
 * with a second copy of the aggregate that can drift from this one.
 *
 * What the domain must guarantee is only this: a machine that has never been
 * written starts somewhere, and nothing in the model moves it. Everything
 * about it moving lives with the adapter that does the moving.
 */
final class VendingMachineVersionTest extends TestCase
{
    public function test_a_machine_that_was_never_stored_starts_at_one(): void
    {
        self::assertSame(1, VendingMachineBuilder::aStockedMachine()->build()->version());
    }

    public function test_using_the_machine_does_not_move_the_version(): void
    {
        $machine = VendingMachineBuilder::aStockedMachine()->build();

        $machine->insert(CoinDenomination::ONE_UNIT);
        $machine->returnInsertedCoins();

        self::assertSame(
            1,
            $machine->version(),
            'the version belongs to the write, and no write has happened yet',
        );
    }
}
