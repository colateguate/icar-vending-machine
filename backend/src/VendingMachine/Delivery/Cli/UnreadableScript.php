<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Cli;

use InvalidArgumentException;

/**
 * The line could not be read as a sequence of button presses.
 *
 * The command-line twin of InvalidRequestPayload at the HTTP edge: it is about
 * the shape of what arrived, never about whether the machine can do it. "Two
 * cents" is perfectly readable and the machine will refuse it; "PUSH-SODA" is
 * not readable at all, and that is this class's business.
 */
final class UnreadableScript extends InvalidArgumentException
{
    public static function nothingToRun(): self
    {
        return new self('The script is empty. Write the steps separated by commas, for example: "1, 0.25, 0.25, GET-SODA".');
    }

    public static function emptyStep(): self
    {
        return new self('The script has an empty step. Two commas in a row, or a comma at the end, leave nothing to press.');
    }

    public static function unknownStep(string $step): self
    {
        return new self(\sprintf(
            'I do not know what "%s" means. A step is a coin (1, 0.25, 0.10, 0.05), RETURN-COIN, or GET-<PRODUCT>.',
            $step,
        ));
    }
}
