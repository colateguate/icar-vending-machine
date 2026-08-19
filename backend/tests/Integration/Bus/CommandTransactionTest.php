<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bus;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\Tests\Support\Doctrine\DoctrineTestEnvironment;
use App\Tests\Support\Doubles\SaveThenFailCommand;
use App\Tests\Support\Doubles\SaveThenFailHandler;
use App\VendingMachine\Domain\Exception\MachineNotFound;
use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\DoctrineVendingMachineRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The test that justifies having a bus at all.
 *
 * Four handlers do not need a message bus; two well-named classes would route
 * them just as well. What the bus buys is a place to say something once for
 * every use case there will ever be, and `doctrine_transaction` is that
 * something: one command, one transaction. No handler opens it, no handler
 * commits it, and no handler can forget to.
 *
 * So this asserts the property rather than the configuration. A test that read
 * messenger.yaml and checked the middleware was listed would pass while the
 * middleware did nothing at all.
 */
final class CommandTransactionTest extends TestCase
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

    public function test_a_command_that_fails_halfway_writes_nothing(): void
    {
        try {
            $this->commandBus()->dispatch(new SaveThenFailCommand('lobby-01'));
            self::fail('the handler was supposed to throw');
        } catch (RuntimeException $failure) {
            self::assertSame(SaveThenFailHandler::FAILURE_MESSAGE, $failure->getMessage());
        }

        // Asked through a different unit of work on purpose: the identity map
        // of the one that did the writing would still be holding the machine
        // and would answer from memory, rolled back or not.
        $elsewhere = new DoctrineVendingMachineRepository($this->database->anotherEntityManager());

        $this->expectException(MachineNotFound::class);
        $elsewhere->find(MachineId::fromString('lobby-01'));
    }

    private function commandBus(): CommandBus
    {
        $bus = $this->database->container()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        return $bus;
    }
}
