<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use Throwable;

/**
 * Marker for failures this domain anticipates and names: an unsupported coin,
 * an out-of-stock product, change that cannot be composed, a machine that was
 * never provisioned. Naming them is what lets the HTTP edge translate each one
 * into a status that says something true — 422, 404, 409, 503 — instead of a
 * blanket error.
 *
 * It is an interface rather than a base class on purpose. The domain owes no
 * inheritance to an error hierarchy, and the edge still gets to catch the
 * whole family in one place.
 *
 * Broken invariants are deliberately NOT part of this family. They are, by
 * definition, the situations we failed to anticipate: a negative amount,
 * subtracting coins the machine does not hold. They throw plain SPL
 * exceptions and become a 500, which is the honest answer to our own bug
 * rather than a 4xx blaming the caller for it.
 */
interface VendingMachineError extends Throwable
{
}
