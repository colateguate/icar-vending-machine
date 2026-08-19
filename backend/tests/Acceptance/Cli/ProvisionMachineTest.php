<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Cli;

use App\Tests\Support\Doctrine\DoctrineTestEnvironment;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\DoctrineVendingMachineRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `app:machine:provision` — the answer to "I cloned this, now what?".
 *
 * The first driving adapter that is not HTTP, and the reason it is worth an
 * acceptance test rather than a unit one: it has to work against the real
 * database, through the real container, or the docker entrypoint that calls it
 * (ticket 13) will fail on a machine nobody has looked at yet.
 */
final class ProvisionMachineTest extends TestCase
{
    private DoctrineTestEnvironment $database;

    protected function setUp(): void
    {
        $this->database = DoctrineTestEnvironment::boot();
    }

    protected function tearDown(): void
    {
        $this->database->shutdown();
    }

    public function test_it_stocks_the_machine_of_the_brief(): void
    {
        $command = $this->provision();

        self::assertSame(0, $command->getStatusCode());

        $machine = $this->storedMachine();
        self::assertCount(3, $machine->inventory()->all());
        self::assertSame(
            '0.65',
            $machine->inventory()->find(ProductSelector::fromString('WATER'))->price()->toDecimalString(),
        );
        self::assertSame(
            '1.00',
            $machine->inventory()->find(ProductSelector::fromString('JUICE'))->price()->toDecimalString(),
        );
        self::assertSame(
            '1.50',
            $machine->inventory()->find(ProductSelector::fromString('SODA'))->price()->toDecimalString(),
        );
    }

    public function test_it_leaves_change_the_machine_can_actually_pay(): void
    {
        $this->provision();

        $reserve = $this->storedMachine()->changeReserve();

        self::assertGreaterThan(0, $reserve->countOf(CoinDenomination::FIVE_CENTS));
        self::assertGreaterThan(0, $reserve->countOf(CoinDenomination::TEN_CENTS));
        self::assertGreaterThan(0, $reserve->countOf(CoinDenomination::TWENTY_FIVE_CENTS));
        self::assertFalse($this->storedMachine()->requiresExactChange());
    }

    public function test_it_says_what_it_did(): void
    {
        self::assertStringContainsString('lobby-01', $this->provision()->getDisplay());
    }

    /**
     * The entrypoint of the container runs this on every start, so a second
     * run has to be a no-op rather than a reset. Told apart from the first run
     * by what it prints, so that whoever is reading the deployment log can see
     * which of the two happened.
     */
    public function test_running_it_again_changes_nothing(): void
    {
        $this->provision();
        $before = $this->storedMachine();

        $second = $this->provision();

        self::assertSame(0, $second->getStatusCode());
        self::assertStringContainsString('already', $second->getDisplay());
        self::assertSame($before->version(), $this->storedMachine()->version(), 'nothing was written');
    }

    private function provision(): CommandTester
    {
        $application = new Application($this->database->kernel());
        $tester = new CommandTester($application->find('app:machine:provision'));
        $tester->execute([]);

        return $tester;
    }

    /**
     * Read through a unit of work of its own. Asking the container's
     * EntityManager — the one the command just used — would be asking its
     * identity map, which answers from memory and would agree with itself
     * whatever the database says.
     */
    private function storedMachine(): VendingMachine
    {
        return (new DoctrineVendingMachineRepository($this->database->anotherEntityManager()))
            ->find(MachineId::fromString('lobby-01'));
    }
}
