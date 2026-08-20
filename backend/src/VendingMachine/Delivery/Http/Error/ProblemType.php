<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use Symfony\Component\HttpFoundation\Response;

/**
 * One row of the error contract: the status a failure leaves as, the name a
 * client switches on, and the sentence a human reads.
 *
 * Both identities are carried on purpose. RFC 7807 asks for a URI in `type`,
 * which is right for documentation and awkward to branch on; `code` is the
 * same identity in the form a frontend actually uses. Deriving one from the
 * other is what keeps them from ever disagreeing.
 */
final readonly class ProblemType
{
    public function __construct(
        public int $status,
        public string $code,
        public string $title,
    ) {
    }

    /**
     * For the refusals Symfony raises before a controller is reached: an
     * unknown route, a method the route does not take. They belong to the same
     * error surface, so they get the same envelope rather than the framework's
     * default page.
     */
    public static function forHttpStatus(int $status): self
    {
        $title = Response::$statusTexts[$status] ?? 'Error';

        return new self($status, strtolower(str_replace([' ', '-'], '_', $title)), $title);
    }

    /**
     * Everything the application did not anticipate. Deliberately says nothing
     * about what happened: the caller cannot act on our bug, and the details
     * of it are exactly what an attacker would like to read.
     */
    public static function internalError(): self
    {
        return new self(500, 'internal_error', 'Internal server error');
    }

    public function typeUri(): string
    {
        return '/problems/'.str_replace('_', '-', $this->code);
    }
}
