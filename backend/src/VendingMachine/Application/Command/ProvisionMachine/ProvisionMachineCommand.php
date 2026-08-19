<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Command\ProvisionMachine;

use App\Shared\Domain\Bus\Command\Command;

/**
 * Put a machine where there was none, and load it.
 *
 * It carries the same rows a service visit does, and is deliberately not the
 * same use case. SERVICE restocks a machine that exists; this one answers the
 * question nothing else can — where does the first machine come from? Folding
 * them together would mean nobody could tell "the technician came" from "there
 * was no machine at all, so one was installed", and the second is the kind of
 * thing an operator wants to see in a log.
 *
 * @implements Command<null>
 */
final readonly class ProvisionMachineCommand implements Command
{
    /**
     * @param list<array{selector: string, name: string, price: string, count: int}> $products
     * @param array<int, int>                                                        $changeReserve denomination in cents => how many
     */
    public function __construct(
        public array $products,
        public array $changeReserve,
    ) {
    }
}
