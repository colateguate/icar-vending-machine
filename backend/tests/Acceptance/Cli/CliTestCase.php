<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Cli;

use App\Tests\Support\Doctrine\DoctrineTestEnvironment;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Domain\Machine\VendingMachine;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\DoctrineVendingMachineRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What every test of a console command needs, in the same place ApiTestCase
 * keeps it for the HTTP ones.
 *
 * A real database built from the real mapping, a real kernel, and the commands
 * fetched from it rather than constructed by hand — a console command that
 * works only when someone wires it in a test proves nothing about the console
 * command that ships.
 */
abstract class CliTestCase extends TestCase
{
    protected const MACHINE_ID = 'lobby-01';

    protected DoctrineTestEnvironment $database;

    protected function setUp(): void
    {
        $this->database = DoctrineTestEnvironment::boot();
    }

    protected function tearDown(): void
    {
        $this->database->shutdown();
    }

    /**
     * @param array<string, string> $input
     */
    protected function runCommand(string $name, array $input = []): CommandTester
    {
        $tester = new CommandTester((new Application($this->database->kernel()))->find($name));
        $tester->execute($input);

        return $tester;
    }

    /**
     * What the command printed, without the line ending the platform chose.
     * Symfony's writeln appends PHP_EOL, so asserting "\n" would pass on the
     * pipeline and fail on a Windows machine over a difference nobody is
     * testing.
     */
    protected static function lineOf(CommandTester $run): string
    {
        return rtrim($run->getDisplay(), "\r\n");
    }

    protected function store(VendingMachine $machine): void
    {
        $repository = $this->database->container()->get(VendingMachineRepository::class);
        self::assertInstanceOf(VendingMachineRepository::class, $repository);

        $repository->save($machine);
    }

    /**
     * Read through a unit of work of its own. Asking the container's
     * EntityManager — the one the command just used — would be asking its
     * identity map, which answers from memory and would agree with itself
     * whatever the database says.
     */
    protected function storedMachine(): VendingMachine
    {
        return (new DoctrineVendingMachineRepository($this->database->anotherEntityManager()))
            ->find(MachineId::fromString(self::MACHINE_ID));
    }
}
