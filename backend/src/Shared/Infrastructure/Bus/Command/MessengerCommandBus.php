<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Command;

use App\Shared\Domain\Bus\Command\Command;
use App\Shared\Domain\Bus\Command\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
     * The unwrapping is the other half of the port's promise. Messenger packs
     * whatever a handler threw inside a HandlerFailedException, so without
     * this the caller would have to catch a messaging library's type to find
     * out a product was sold out — and the interface exists precisely so that
     * nothing outside this layer names one. Since exactly one handler runs per
     * command, the first wrapped failure is the failure.
     *
     * A missing or duplicated handler is deliberately not unwrapped: that is
     * this adapter's own wiring being wrong, not the domain refusing anything.
     *
     * @template TResult
     *
     * @param Command<TResult> $command
     *
     * @return TResult
     */
    public function dispatch(Command $command): mixed
    {
        try {
            /* @var TResult */
            return $this->handle($command);
        } catch (HandlerFailedException $failure) {
            throw $failure->getPrevious() ?? $failure;
        }
    }
}
