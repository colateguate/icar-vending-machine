<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Application\Command\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Delivery\Http\Response\CoinsResponse;
use App\VendingMachine\Delivery\Http\Response\MachineStateResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * POST /api/machine/coins/return — the RETURN-COIN button.
 *
 * Takes no body: the button has no argument. The coins it gives back are in
 * the response because they have physically left the machine, which is the
 * only reason a command in this codebase is allowed to answer with anything.
 */
final readonly class ReturnCoinsController
{
    public function __construct(
        private CommandBus $commands,
        private MachineStateResponder $responder,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $returned = $this->commands->dispatch(new ReturnCoinsCommand());

        return $this->responder->respond(['returned' => CoinsResponse::from($returned)]);
    }
}
