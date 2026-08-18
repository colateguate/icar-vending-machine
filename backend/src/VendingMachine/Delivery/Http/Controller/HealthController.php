<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Liveness probe for the docker healthcheck and manual smoke tests.
 * Deliberately does not touch the bus or any port: it answers the
 * question "is the kernel up?", nothing more.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
