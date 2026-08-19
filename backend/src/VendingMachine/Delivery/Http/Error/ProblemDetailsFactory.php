<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use App\VendingMachine\Domain\Exception\CannotDispenseChange;
use App\VendingMachine\Domain\Exception\InsufficientFunds;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns a thrown thing into the document a client reads: RFC 7807
 * problem+json, one shape for every failure this API can have.
 *
 * Three sources, in order. A catalogued failure keeps its own message, because
 * the domain wrote that sentence for a person to read. A refusal from the
 * framework keeps its status. Anything else says nothing at all — the caller
 * cannot act on our bug, and its message is the field most likely to name a
 * class, a table or a path.
 */
final readonly class ProblemDetailsFactory
{
    private const SUPPRESSED_DETAIL = 'An unexpected error prevented this request from completing.';

    public function fromThrowable(Throwable $error): JsonResponse
    {
        $problem = self::problemFor($error);

        return new JsonResponse(
            [
                'type' => $problem->typeUri(),
                'title' => $problem->title,
                'status' => $problem->status,
                'detail' => self::detailOf($error, $problem),
                'code' => $problem->code,
                ...self::extensionsOf($error),
            ],
            $problem->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    private static function problemFor(Throwable $error): ProblemType
    {
        if (ErrorCatalog::knows($error::class)) {
            return ErrorCatalog::of($error::class);
        }

        if ($error instanceof HttpExceptionInterface) {
            return ProblemType::forHttpStatus($error->getStatusCode());
        }

        return ProblemType::internalError();
    }

    /**
     * A framework refusal keeps its message because it describes the request
     * the caller made — the method, the path they asked for — and nothing on
     * this side of it.
     */
    private static function detailOf(Throwable $error, ProblemType $problem): string
    {
        if (ErrorCatalog::knows($error::class) || $error instanceof HttpExceptionInterface) {
            return '' === $error->getMessage() ? $problem->title : $error->getMessage();
        }

        return self::SUPPRESSED_DETAIL;
    }

    /**
     * The refusals that carry a number put it in the document rather than only
     * in the sentence, so a client can show it without parsing English.
     *
     * A match rather than another column in the catalog: what each of these
     * exposes is particular to it, and a table of callables would be less
     * readable than the table it replaced.
     *
     * @return array<string, string>
     */
    private static function extensionsOf(Throwable $error): array
    {
        return match (true) {
            $error instanceof CannotDispenseChange => ['changeDue' => $error->amountDue()->toDecimalString()],
            $error instanceof InsufficientFunds => ['missingAmount' => $error->missingAmount()->toDecimalString()],
            $error instanceof InvalidRequestPayload => ['field' => $error->field()],
            default => [],
        };
    }
}
