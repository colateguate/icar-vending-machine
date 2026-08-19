<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;
use Throwable;

/**
 * Someone else changed the machine between the moment this caller read it and
 * the moment they tried to write. Nothing they sent was wrong, and nothing is
 * broken: the answer is simply out of date, so the write is refused and the
 * caller can look again.
 *
 * It lives in the domain rather than beside the adapter that detects it, for
 * two reasons. The edge has to name it to answer 409, and the edge is not
 * allowed to know that Doctrine exists — the dependency rule forbids it. And
 * conceptually this is the same family as MachineNotFound: a failure the model
 * anticipates about its own persistence, named so it can be answered honestly
 * instead of arriving as an unexplained 500.
 *
 * How it is detected is deliberately not stated here. Today it is a version
 * column; a different adapter could use a timestamp or a row lock, and the
 * caller's answer would not change.
 */
final class ConcurrentMachineModification extends DomainException implements VendingMachineError
{
    /**
     * The cause is kept because this exception is the honest half of the
     * story: what the caller is told. Whatever the adapter caught — a version
     * mismatch, a lock timeout — is what whoever reads the log needs, and it
     * would be gone if it were not carried along.
     *
     * Named arguments so the code parameter can be skipped rather than passed
     * as a zero that means nothing. SPL puts it between the message and the
     * cause, and this domain has no use for error codes.
     */
    public static function of(string $machineId, ?Throwable $previous = null): self
    {
        return new self(
            message: \sprintf('The machine "%s" was changed by someone else while this request was being handled.', $machineId),
            previous: $previous,
        );
    }
}
