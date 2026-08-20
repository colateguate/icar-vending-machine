<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Cli;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Domain\Dispensing\DispensedGoods;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\Money;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The brief's own syntax, pasted into a terminal:
 *
 *     bin/console app:machine:run "1, 0.25, 0.25, GET-SODA"
 *     -> SODA
 *
 * This is the second driving adapter, and the point of it is what it does NOT
 * contain. There is no price here, no stock, no arithmetic and no rule: it
 * reads a line into commands and puts them on the same bus the HTTP
 * controllers use, against the same machine the API serves. If the hexagon
 * were a costume, this file is where it would show — a CLI that had to
 * reimplement anything would be proof that the "core" was really the web
 * layer.
 *
 * It drives the provisioned machine rather than a throwaway one, and that is a
 * choice worth defending: an adapter that acted on a different machine than
 * the API would be a simulator wearing an adapter's clothes, and the claim
 * being made is precisely that both doors lead to the same room. The cost is
 * that running an example changes real state — a can really leaves the
 * machine — which is what `app:machine:provision` is there to undo.
 *
 * A script is a sequence of button presses, not a transaction. Each step is
 * its own command and therefore its own transaction (ADR-0011's middleware),
 * so a refusal halfway leaves the machine exactly as the failing press left
 * it, with the coins already inserted still in the escrow. That is what the
 * machine in the hallway does, and RETURN-COIN is the way out of it.
 */
#[AsCommand(
    name: 'app:machine:run',
    description: 'Drive the machine with the literal syntax of the brief, e.g. "1, 0.25, 0.25, GET-SODA"',
)]
final class RunMachineScriptCommand extends Command
{
    public function __construct(private readonly CommandBus $commands)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'script',
            InputArgument::REQUIRED,
            'Steps separated by commas: coins (1, 0.25, 0.10, 0.05), RETURN-COIN, GET-<PRODUCT>',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $handedOver = $this->play(MachineScript::parse(self::scriptIn($input)));
        } catch (MachineNotFound $missing) {
            // Almost certainly someone's first run, so the answer says what to
            // do rather than only what went wrong.
            return self::refuse($output, $missing->getMessage().' Run "app:machine:provision" first.');
        } catch (UnreadableScript|VendingMachineError $refusal) {
            return self::refuse($output, $refusal->getMessage());
        }

        $output->writeln('-> '.implode(', ', $handedOver));

        return self::SUCCESS;
    }

    /**
     * @return list<string> everything the machine physically handed over, in
     *                      the order it came out
     */
    private function play(MachineScript $script): array
    {
        $handedOver = [];
        foreach ($script->steps() as $step) {
            $handedOver = [...$handedOver, ...self::whatCameOut($this->commands->dispatch($step))];
        }

        return $handedOver;
    }

    /**
     * Only the two outcomes that left the machine are printed. Inserting a
     * coin produces nothing to report, which is why a script of nothing but
     * coins prints an empty arrow rather than an invented summary.
     *
     * @return list<string>
     */
    private static function whatCameOut(mixed $outcome): array
    {
        return match (true) {
            $outcome instanceof DispensedGoods => [
                $outcome->selector()->value(),
                ...self::spellOut($outcome->change()),
            ],
            $outcome instanceof CoinCollection => self::spellOut($outcome),
            default => [],
        };
    }

    /**
     * Coins as they would land in your hand: one entry per physical coin, and
     * the biggest first. The brief prints "0.25, 0.10" and this adapter exists
     * to reproduce the brief; the HTTP contract keeps the collection's own
     * ascending order, because there the client is a program and the order is
     * only a convention it does not read.
     *
     * @return list<string>
     */
    private static function spellOut(CoinCollection $coins): array
    {
        $spelled = [];
        foreach (array_reverse($coins->toArray(), true) as $cents => $count) {
            $spelled = [...$spelled, ...array_fill(0, $count, Money::fromCents($cents)->toDecimalString())];
        }

        return $spelled;
    }

    private static function refuse(OutputInterface $output, string $reason): int
    {
        // No stack trace: everything caught here is either a line somebody
        // mistyped or the machine saying no, and neither is a bug of ours.
        //
        // Escaped because these messages quote back what the caller wrote, and
        // the console formatter reads <something> as a style instruction: a
        // selector like "<info>" is silently eaten, so the sentence explaining
        // which value was rejected arrives without the value in it.
        //
        // Raw control bytes are deliberately NOT stripped. Escaping the
        // formatter's own syntax fixes a wrong message; stripping ANSI would
        // be defending the reader of this terminal against the person typing
        // into it, who are the same person.
        $output->writeln('<error>'.OutputFormatter::escape($reason).'</error>');

        return self::FAILURE;
    }

    private static function scriptIn(InputInterface $input): string
    {
        $script = $input->getArgument('script');

        // A required argument is always a string; the check is here because
        // the console's signature promises less than that.
        return \is_string($script)
            ? $script
            : throw new InvalidArgumentException('The script has to be a single quoted argument.');
    }
}
