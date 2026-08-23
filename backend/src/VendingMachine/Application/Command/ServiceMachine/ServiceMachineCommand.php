<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ServiceMachine;

use App\Shared\Domain\Bus\Command\Command;

/**
 * A technician says what the machine now stocks and how much change it holds.
 *
 * Both payloads are plain arrays. Amounts arrive as decimal strings for the
 * same reason they leave as decimal strings: a JSON number would put the
 * client back on floating point.
 *
 * The shapes below are a contract the delivery layer must honour, not a hope.
 * Checking that a request body actually has these keys and types belongs at
 * the edge, which answers 422 before a command is ever built; by the time one
 * exists the shape is settled and the handler's only job is turning values
 * into domain types. A handler that meets a malformed payload is looking at a
 * bug in the adapter rather than at bad user input, and fails as one.
 *
 * @implements Command<null>
 */
final readonly class ServiceMachineCommand implements Command
{
    /**
     * @param list<array{selector: string, name: string, price: string, count: int}> $products
     * @param array<int, int>                                                        $changeReserve denomination in cents => how many
     * @param list<int>|null                                                         $acceptedCoins denominations in cents the machine
     *                                                                                              takes from now on; null when the
     *                                                                                              caller said nothing about coins,
     *                                                                                              which leaves the acceptor as it is
     */
    public function __construct(
        public array $products,
        public array $changeReserve,
        public ?array $acceptedCoins = null,
    ) {
    }
}
