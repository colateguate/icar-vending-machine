<?php

declare(strict_types=1);

namespace App\Tests\Support\Doubles;

use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Tests\Support\Builder\VendingMachineBuilder;
use App\VendingMachine\Domain\Machine\VendingMachineRepository;
use RuntimeException;

/**
 * Saves a machine and then throws, in that order. Registered only in the test
 * environment (see the when@test block of config/services.yaml).
 */
final readonly class SaveThenFailHandler implements CommandHandler
{
    public const FAILURE_MESSAGE = 'the handler failed after writing';

    public function __construct(private VendingMachineRepository $repository)
    {
    }

    public function __invoke(SaveThenFailCommand $command): void
    {
        $this->repository->save(
            VendingMachineBuilder::aStockedMachine()->withId($command->machineId)->build(),
        );

        throw new RuntimeException(self::FAILURE_MESSAGE);
    }
}
