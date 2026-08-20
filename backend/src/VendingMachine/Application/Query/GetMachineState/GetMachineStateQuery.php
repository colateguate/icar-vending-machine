<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Query\GetMachineState;

use App\Shared\Domain\Bus\Query\Query;

/**
 * @implements Query<MachineStateView>
 */
final readonly class GetMachineStateQuery implements Query
{
}
