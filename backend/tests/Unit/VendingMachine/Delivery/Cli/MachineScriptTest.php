<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Delivery\Cli;

use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Delivery\Cli\MachineScript;
use App\VendingMachine\Delivery\Cli\UnreadableScript;
use App\VendingMachine\Domain\Exception\InvalidMoneyAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rules that turn a line of the brief into commands.
 *
 * The acceptance tests prove the three examples come out right end to end;
 * this proves each rule on its own, without a kernel or a database in the way,
 * so a failure points at the rule rather than at everything downstream of it.
 */
final class MachineScriptTest extends TestCase
{
    public function test_a_coin_becomes_an_insertion(): void
    {
        $steps = MachineScript::parse('0.25')->steps();

        self::assertCount(1, $steps);
        self::assertInstanceOf(InsertCoinCommand::class, $steps[0]);
        self::assertSame(25, $steps[0]->coinInCents);
    }

    /**
     * The brief writes the biggest coin as "1", not "1.00", and pasting the
     * brief verbatim is the whole point of this adapter.
     */
    public function test_the_unit_coin_can_be_written_the_way_the_brief_writes_it(): void
    {
        $steps = MachineScript::parse('1')->steps();

        self::assertInstanceOf(InsertCoinCommand::class, $steps[0]);
        self::assertSame(100, $steps[0]->coinInCents);
    }

    public function test_return_coin_becomes_a_refund(): void
    {
        self::assertInstanceOf(ReturnCoinsCommand::class, MachineScript::parse('RETURN-COIN')->steps()[0]);
    }

    public function test_a_button_becomes_a_purchase(): void
    {
        $steps = MachineScript::parse('GET-SODA')->steps();

        self::assertInstanceOf(PurchaseProductCommand::class, $steps[0]);
        self::assertSame('SODA', $steps[0]->selector);
    }

    public function test_the_steps_keep_the_order_they_were_written_in(): void
    {
        $steps = MachineScript::parse('1, 0.25, 0.25, GET-SODA')->steps();

        self::assertCount(4, $steps);
        self::assertContainsOnlyInstancesOf(InsertCoinCommand::class, \array_slice($steps, 0, 3));
        self::assertInstanceOf(PurchaseProductCommand::class, $steps[3]);
    }

    public function test_it_forgives_whatever_spacing_someone_pasted(): void
    {
        self::assertCount(3, MachineScript::parse('  1 ,0.25,   GET-SODA  ')->steps());
    }

    /**
     * Deciding which coins this machine takes is not a parser's job — it is
     * CoinDenomination's, and asking twice is how two answers start to
     * differ. Two cents is a perfectly readable step; it is just not a coin.
     */
    public function test_it_reads_an_amount_that_is_not_a_coin_and_lets_the_machine_refuse_it(): void
    {
        $steps = MachineScript::parse('0.02')->steps();

        self::assertInstanceOf(InsertCoinCommand::class, $steps[0]);
        self::assertSame(2, $steps[0]->coinInCents);
    }

    public function test_an_amount_that_is_not_an_amount_is_refused_by_the_money(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        MachineScript::parse('1.234');
    }

    /**
     * @param non-empty-string $script
     */
    #[DataProvider('unreadableScripts')]
    public function test_it_refuses_what_it_cannot_read(string $script, string $expectedInMessage): void
    {
        $this->expectException(UnreadableScript::class);
        $this->expectExceptionMessage($expectedInMessage);

        MachineScript::parse($script);
    }

    /**
     * @return iterable<string, array{non-empty-string, string}>
     */
    public static function unreadableScripts(): iterable
    {
        yield 'a word that is not a step' => ['HELLO', 'HELLO'];
        yield 'a button with the wrong verb' => ['PUSH-SODA', 'PUSH-SODA'];
        yield 'the right verb in the wrong case' => ['get-soda', 'get-soda'];
        yield 'nothing at all' => ['   ', 'empty'];
        yield 'a gap between two commas' => ['1, , GET-SODA', 'empty'];
        yield 'a trailing comma' => ['1, GET-SODA,', 'empty'];
    }
}
