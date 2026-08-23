<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Cli;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Application\Command\ProvisionMachine\ProvisionMachineCommand as ProvisionMachine;
use App\VendingMachine\Application\Shared\MachineLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The second driving adapter, and the one that answers "I cloned this, now
 * what?".
 *
 * It holds the catalogue of the brief — Water 0.65, Juice 1.00, Soda 1.50 —
 * because that catalogue is a fact about this exercise rather than about the
 * domain: a machine sells whatever a technician loaded into it, and the model
 * has no opinion on what that is.
 *
 * Like every adapter here it decides nothing: it builds a command and hands it
 * to the bus. Whether provisioning is safe to repeat is the use case's rule,
 * not this class's, which is why the message it prints is chosen by asking the
 * same question the handler asks rather than by inspecting what the handler
 * did.
 */
#[AsCommand(
    name: 'app:machine:provision',
    description: 'Install the machine this deployment serves and load it with the catalogue of the brief',
)]
final class ProvisionMachineCommand extends Command
{
    /**
     * @var list<array{selector: string, name: string, price: string, count: int}>
     */
    private const CATALOGUE = [
        ['selector' => 'WATER', 'name' => 'Water', 'price' => '0.65', 'count' => 10],
        ['selector' => 'JUICE', 'name' => 'Juice', 'price' => '1.00', 'count' => 10],
        ['selector' => 'SODA', 'name' => 'Soda', 'price' => '1.50', 'count' => 10],
    ];

    /**
     * Enough of every coin the machine is allowed to hand back, so a customer
     * paying 1.00 for a 0.65 item gets change on the first day rather than an
     * EXACT CHANGE ONLY lamp.
     *
     * @var array<int, int>
     */
    private const CHANGE_RESERVE = [5 => 20, 10 => 20, 25 => 20];

    /**
     * The four coins the brief names. The acceptor can read two more — 0.50 and
     * 2.00 — and a machine installed from here does not take them until a
     * technician says so, which is what keeps this machine the one the brief
     * describes while the model is able to describe others.
     *
     * @var list<int>
     */
    private const ACCEPTED_COINS = [5, 10, 25, 100];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly MachineLocator $locator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $machineId = $this->locator->machineId()->value();

        if ($this->locator->isProvisioned()) {
            $console->info(\sprintf('Machine "%s" is already provisioned; nothing to do.', $machineId));

            return self::SUCCESS;
        }

        $this->commands->dispatch(new ProvisionMachine(self::CATALOGUE, self::CHANGE_RESERVE, self::ACCEPTED_COINS));

        $console->success(\sprintf('Machine "%s" provisioned with %d products.', $machineId, \count(self::CATALOGUE)));

        return self::SUCCESS;
    }
}
