<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Cli;

use App\Shared\Domain\Bus\Command\Command;
use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductCommand;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Domain\Money\Money;

/**
 * A line of the brief, read as the sequence of commands it describes.
 *
 * "1, 0.25, 0.25, GET-SODA" is the syntax of the statement, copied exactly,
 * and turning it into InsertCoinCommand, InsertCoinCommand, InsertCoinCommand,
 * PurchaseProductCommand is the entire job of this class. It knows nothing
 * about machines, prices or stock: it produces the same messages the HTTP
 * controllers produce, and everything downstream cannot tell which adapter
 * they came from. That is the claim ADR-0001 makes, and this is the cheapest
 * demonstration of it in the repository.
 *
 * The split between what it refuses and what it lets through is deliberate. A
 * step it cannot read is its problem ("PUSH-SODA"). A step it can read but the
 * machine will not accept is not ("0.02" is a fine step and a coin this
 * machine does not take) — CoinDenomination already answers that, and asking
 * twice is how two answers start to differ.
 */
final readonly class MachineScript
{
    private const RETURN_COIN = 'RETURN-COIN';

    private const PURCHASE_PREFIX = 'GET-';

    /**
     * @param list<Command<mixed>> $steps
     */
    private function __construct(private array $steps)
    {
    }

    public static function parse(string $script): self
    {
        if ('' === trim($script)) {
            throw UnreadableScript::nothingToRun();
        }

        return new self(array_map(self::stepFrom(...), explode(',', $script)));
    }

    /**
     * @return list<Command<mixed>>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return Command<mixed>
     */
    private static function stepFrom(string $step): Command
    {
        $step = trim($step);

        if ('' === $step) {
            throw UnreadableScript::emptyStep();
        }

        if (self::RETURN_COIN === $step) {
            return new ReturnCoinsCommand();
        }

        if (str_starts_with($step, self::PURCHASE_PREFIX)) {
            return new PurchaseProductCommand(substr($step, \strlen(self::PURCHASE_PREFIX)));
        }

        // Anything that opens with a digit is an attempt at an amount, so Money
        // gets to say whether it is one. Anything else is not a coin, not a
        // button and not the refund: it is a step nobody can act on.
        return ctype_digit($step[0])
            ? new InsertCoinCommand(Money::fromDecimalString($step)->cents())
            : throw UnreadableScript::unknownStep($step);
    }
}
