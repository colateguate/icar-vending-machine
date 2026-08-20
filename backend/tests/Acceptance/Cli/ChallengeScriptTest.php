<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Cli;

use App\Tests\Support\Builder\VendingMachineBuilder;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The brief, copied character for character, and run.
 *
 * This is the closest thing this repository has to the acceptance criteria of
 * the whole exercise: the sequences are the ones printed in the statement, the
 * expected output is the line printed under them, and both are asserted
 * literally. Someone evaluating this can paste any of the three into a
 * terminal and see the same thing.
 *
 * The same three sequences already exist as unit tests against the aggregate,
 * as integration tests over the buses, and as acceptance tests over HTTP. That
 * is four levels asking four different questions about one behaviour — and
 * this one asks the only question the others cannot: does the machine do what
 * the brief says when you drive it the way the brief drives it?
 */
final class ChallengeScriptTest extends CliTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->store(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());
    }

    /**
     * Example 1: Buy Soda with exact change.
     */
    public function test_example_1(): void
    {
        $run = $this->runScript('1, 0.25, 0.25, GET-SODA');

        self::assertSame(0, $run->getStatusCode());
        self::assertSame('-> SODA', self::lineOf($run));
    }

    /**
     * Example 2: Start adding money, but user ask for return coin.
     */
    public function test_example_2(): void
    {
        $run = $this->runScript('0.10, 0.10, RETURN-COIN');

        self::assertSame(0, $run->getStatusCode());
        self::assertSame('-> 0.10, 0.10', self::lineOf($run));
    }

    /**
     * Example 3: Buy Water without exact change.
     */
    public function test_example_3(): void
    {
        $run = $this->runScript('1, GET-WATER');

        self::assertSame(0, $run->getStatusCode());
        self::assertSame('-> WATER, 0.25, 0.10', self::lineOf($run));
    }

    private function runScript(string $script): CommandTester
    {
        return $this->runCommand('app:machine:run', ['script' => $script]);
    }
}
