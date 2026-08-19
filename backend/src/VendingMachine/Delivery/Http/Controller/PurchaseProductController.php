<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\VendingMachine\Delivery\Http\Request\PurchaseProductRequest;
use App\VendingMachine\Delivery\Http\Response\DispensedResponse;
use App\VendingMachine\Delivery\Http\Response\MachineStateResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * POST /api/machine/purchases — pressing GET-SODA.
 *
 * Plural, and a POST rather than a verb in the path: a purchase is something
 * that happened, which leaves room for asking about past ones later without
 * the URL having to change into something that reads like a remote procedure.
 */
final readonly class PurchaseProductController
{
    public function __construct(
        private CommandBus $commands,
        private MachineStateResponder $responder,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $dispensed = $this->commands->dispatch(PurchaseProductRequest::of($request)->toCommand());

        return $this->responder->respond(['dispensed' => DispensedResponse::from($dispensed)]);
    }
}
