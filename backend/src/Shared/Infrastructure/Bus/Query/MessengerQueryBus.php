<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Query;

use App\Shared\Domain\Bus\Query\Query;
use App\Shared\Domain\Bus\Query\QueryBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    /**
     * Unwrapped for the same reason as on the command side: asking a machine
     * that was never provisioned must arrive as MachineNotFound, not as a
     * messaging library's wrapper around it.
     *
     * @template TResponse
     *
     * @param Query<TResponse> $query
     *
     * @return TResponse
     */
    public function ask(Query $query): mixed
    {
        try {
            /* @var TResponse */
            return $this->handle($query);
        } catch (HandlerFailedException $failure) {
            throw $failure->getPrevious() ?? $failure;
        }
    }
}
