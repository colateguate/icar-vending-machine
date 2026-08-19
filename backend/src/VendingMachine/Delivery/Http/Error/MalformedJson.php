<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use InvalidArgumentException;

/**
 * The bytes were not a JSON object. This is the only failure of the API that
 * is about the request itself rather than about what it says, which is why it
 * is a 400 and everything else a client gets wrong is a 422.
 *
 * Nothing of the parser's own message travels: it can quote the offending
 * fragment, and the request body is not ours to echo back.
 */
final class MalformedJson extends InvalidArgumentException
{
    public static function couldNotBeParsed(): self
    {
        return new self('The request body is not valid JSON.');
    }

    public static function notAnObject(): self
    {
        return new self('The request body must be a JSON object.');
    }
}
