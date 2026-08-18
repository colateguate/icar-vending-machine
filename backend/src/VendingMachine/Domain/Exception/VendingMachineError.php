<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use Throwable;

/**
 * Marker for failures the caller caused and can act on: an unsupported coin,
 * an out-of-stock product, change that cannot be composed.
 *
 * It is an interface rather than a base class on purpose. The domain owes no
 * inheritance to an error hierarchy, and the HTTP edge still gets to catch the
 * whole family in one place to translate it into problem+json.
 *
 * Broken invariants are deliberately NOT part of this family: those are bugs,
 * they throw plain SPL exceptions, and they deserve a 500 rather than a 4xx
 * that would blame the caller for our mistake.
 */
interface VendingMachineError extends Throwable
{
}
