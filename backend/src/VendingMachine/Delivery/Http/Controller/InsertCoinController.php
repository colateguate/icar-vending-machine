<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Delivery\Http\Request\InsertCoinRequest;
use App\VendingMachine\Delivery\Http\Response\MachineStateResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * POST /api/machine/coins — one coin per call, like a real slot.
 *
 * Read the body, dispatch, answer with the new state. There is no try/catch:
 * a refusal is an exception on its way to the problem+json subscriber, and
 * catching it here would be the error table copied into five controllers.
 */
final readonly class InsertCoinController
{
    public function __construct(
        private CommandBus $commands,
        private MachineStateResponder $responder,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->commands->dispatch(InsertCoinRequest::of($request)->toCommand());

        return $this->responder->respond();
    }
}
