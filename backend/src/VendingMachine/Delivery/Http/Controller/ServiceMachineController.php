<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Delivery\Http\Request\ServiceMachineRequest;
use App\VendingMachine\Delivery\Http\Response\MachineStateResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * PUT /api/machine/service — a service visit.
 *
 * PUT because the visit is idempotent by definition: it states what the
 * machine stocks and holds, so sending it twice leaves the same machine.
 */
final readonly class ServiceMachineController
{
    public function __construct(
        private CommandBus $commands,
        private MachineStateResponder $responder,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->commands->dispatch(ServiceMachineRequest::of($request)->toCommand());

        return $this->responder->respond();
    }
}
