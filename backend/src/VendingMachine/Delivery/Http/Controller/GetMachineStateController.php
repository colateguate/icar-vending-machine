<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use App\VendingMachine\Delivery\Http\Response\MachineStateResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/machine — what a customer standing in front of it can see.
 */
final readonly class GetMachineStateController
{
    public function __construct(private MachineStateResponder $responder)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->responder->respond();
    }
}
