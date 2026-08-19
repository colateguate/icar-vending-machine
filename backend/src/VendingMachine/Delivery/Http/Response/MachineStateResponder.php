<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Response;

use App\Shared\Domain\Bus\Query\QueryBus;
use App\VendingMachine\Application\Query\GetMachineState\GetMachineStateQuery;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Every response of this API carries the state the machine was left in, under
 * "machine", and the ones with a physical outcome add it alongside. One rule,
 * no exceptions, so a client never has to ask a second time to redraw itself
 * and never has to learn which endpoints answer with what.
 *
 * This is also the seam where a command and a query meet, and the reason it is
 * here rather than in a handler. The rule that a command returns nothing
 * recoverable by a later question is about the bus; composing "do this" with
 * "and tell me how things stand now" into one HTTP exchange is a delivery
 * concern, and this is the delivery layer.
 */
final readonly class MachineStateResponder
{
    public function __construct(private QueryBus $queries)
    {
    }

    /**
     * @param array<string, mixed> $outcome what physically left the machine, if anything
     */
    public function respond(array $outcome = []): JsonResponse
    {
        return new JsonResponse([
            ...$outcome,
            'machine' => MachineStateResponse::from($this->queries->ask(new GetMachineStateQuery())),
        ]);
    }
}
