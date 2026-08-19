<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Cli;

use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Money\CoinDenomination;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Everything about `app:machine:run` that is not one of the brief's three
 * examples: what it refuses, what it leaves behind, and what it says when it
 * cannot do what was asked.
 *
 * A command-line adapter has the same duty the HTTP one has — turn a refusal
 * into something the person on the other end can act on — and no subscriber to
 * do it for it. What replaces the problem+json envelope here is a sentence and
 * an exit code, and both are asserted.
 */
final class RunMachineScriptTest extends CliTestCase
{
    public function test_it_drives_the_machine_the_api_serves(): void
    {
        $this->givenAStockedMachine();

        $this->runScript('0.25, 0.10');

        self::assertSame(
            '0.35',
            $this->storedMachine()->insertedAmount()->toDecimalString(),
            'the coins went into the same machine the API would have used',
        );
    }

    /**
     * @param non-empty-string $script
     */
    #[DataProvider('scriptsNobodyCanRead')]
    public function test_it_refuses_a_script_it_cannot_read(string $script, string $expectedInMessage): void
    {
        $this->givenAStockedMachine();

        $run = $this->runScript($script);

        self::assertSame(Command::FAILURE, $run->getStatusCode());
        self::assertStringContainsString($expectedInMessage, self::lineOf($run));
        self::assertStringNotContainsString('#0 ', $run->getDisplay(), 'a stack trace is not an error message');
    }

    /**
     * @return iterable<string, array{non-empty-string, string}>
     */
    public static function scriptsNobodyCanRead(): iterable
    {
        yield 'a step that is nothing at all' => ['1, HELLO', 'HELLO'];
        yield 'a button that does not exist' => ['1, PUSH-SODA', 'PUSH-SODA'];
        yield 'an empty script' => ['   ', 'empty'];
        yield 'a trailing comma leaving a gap' => ['1, , GET-SODA', 'empty'];
    }

    /**
     * The console formatter reads <something> as a style instruction, and
     * every one of these messages quotes back what the caller wrote. Without
     * escaping, "GET-<info>" produces a sentence that says a selector was
     * rejected and leaves out which one — the formatter having eaten it.
     */
    public function test_a_refusal_still_shows_a_value_that_looks_like_markup(): void
    {
        $this->givenAStockedMachine();

        $run = $this->runScript('GET-<info>');

        self::assertSame(Command::FAILURE, $run->getStatusCode());
        self::assertStringContainsString('<info>', self::lineOf($run));
    }

    /**
     * Every way the machine itself can say no, arriving at the terminal in the
     * words the domain wrote.
     *
     * One table rather than five near-identical tests, because the interesting
     * property is the completeness of the list: all of these travel the same
     * catch, so a test per exception would assert the same line five times,
     * while what is worth knowing is that no named refusal of this domain gets
     * lost between the aggregate and the person reading the output.
     *
     * The expected fragments are the load-bearing part of each message — the
     * amount still owed, the amount that could not be paid, the selector
     * nobody stocks — so a refusal that arrived with the wrong words, or with
     * the right words about the wrong value, still fails.
     *
     * @param non-empty-list<string> $expectedInMessage
     */
    #[DataProvider('refusalsTheMachineCanMake')]
    public function test_every_refusal_the_machine_makes_reaches_the_terminal(
        VendingMachine $machine,
        string $script,
        array $expectedInMessage,
    ): void {
        $this->store($machine);

        $run = $this->runScript($script);

        self::assertSame(Command::FAILURE, $run->getStatusCode());
        foreach ($expectedInMessage as $fragment) {
            self::assertStringContainsString($fragment, self::lineOf($run));
        }
    }

    /**
     * @return iterable<string, array{VendingMachine, non-empty-string, non-empty-list<string>}>
     */
    public static function refusalsTheMachineCanMake(): iterable
    {
        yield 'a coin the machine does not take' => [
            VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build(),
            '0.02, GET-WATER',
            ['2 cents'],
        ];

        yield 'a product this machine does not stock' => [
            VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build(),
            '1, GET-SPRITZER',
            ['SPRITZER'],
        ];

        yield 'not enough money in yet' => [
            VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build(),
            '0.25, GET-WATER',
            ['0.40'],
        ];

        yield 'a slot that is empty' => [
            VendingMachineBuilder::aMachine()
                ->withId(self::MACHINE_ID)
                ->withProduct('SODA', 'Soda', '1.50', 0)
                ->withChangeReserve([25 => 10])
                ->build(),
            '1, 0.25, 0.25, GET-SODA',
            ['sold out'],
        ];

        // The refusal this whole domain is built around (ADR-0007).
        yield 'change that cannot be composed' => [
            VendingMachineBuilder::aMachine()
                ->withId(self::MACHINE_ID)
                ->withProduct('WATER', 'Water', '0.65', 5)
                ->withNoChange()
                ->build(),
            '1, GET-WATER',
            ['0.35', 'change'],
        ];
    }

    /**
     * A script is a sequence of button presses, not a transaction. The coins
     * that went in before the refusal are where a real machine would leave
     * them — in the escrow, waiting for RETURN-COIN — and saying so is more
     * useful than pretending nothing happened.
     */
    public function test_a_refused_script_leaves_the_coins_where_they_fell(): void
    {
        $this->store(
            VendingMachineBuilder::aMachine()
                ->withId(self::MACHINE_ID)
                ->withProduct('SODA', 'Soda', '1.50', 0)
                ->build(),
        );

        $this->runScript('0.25, 0.25, GET-SODA');

        self::assertSame(2, $this->storedMachine()->insertedCoins()->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
    }

    /**
     * The most likely first run of anyone who just cloned this.
     */
    public function test_it_says_what_to_do_when_there_is_no_machine_yet(): void
    {
        $run = $this->runScript('0.25');

        self::assertSame(Command::FAILURE, $run->getStatusCode());
        self::assertStringContainsString('app:machine:provision', self::lineOf($run));
    }

    public function test_a_script_that_produces_nothing_says_nothing(): void
    {
        $this->givenAStockedMachine();

        $run = $this->runScript('0.25, 0.25');

        self::assertSame(Command::SUCCESS, $run->getStatusCode());
        self::assertSame('-> ', self::lineOf($run));
    }

    public function test_it_reports_everything_the_machine_handed_over(): void
    {
        $this->givenAStockedMachine();

        $run = $this->runScript('0.10, RETURN-COIN, 1, GET-WATER');

        self::assertSame('-> 0.10, WATER, 0.25, 0.10', self::lineOf($run));
    }

    private function runScript(string $script): CommandTester
    {
        return $this->runCommand('app:machine:run', ['script' => $script]);
    }

    private function givenAStockedMachine(): void
    {
        $this->store(VendingMachineBuilder::aStockedMachine()->withId(self::MACHINE_ID)->build());
    }
}
