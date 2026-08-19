<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Command;

use App\Shared\Domain\Bus\Command\Command;
use App\Shared\Domain\Bus\Command\CommandBus;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The only class in the project that knows commands travel on Messenger.
 *
 * HandleTrait is what makes a handler's return value come back to the caller,
 * and it insists on exactly one handler per command — a second one, or none at
 * all, is a wiring mistake and fails loudly rather than silently doing nothing.
 */
final class MessengerCommandBus implements CommandBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    /**
     * @template TResult
     *
     * @param Command<TResult> $command
     *
     * @return TResult
     */
    public function dispatch(Command $command): mixed
    {
        /* @var TResult */
        return $this->handle($command);
    }
}
