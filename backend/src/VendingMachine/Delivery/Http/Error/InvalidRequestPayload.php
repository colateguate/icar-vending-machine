<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use InvalidArgumentException;

/**
 * The body parsed, and it does not have the shape the command declares.
 *
 * Always names the field, including inside a nested list
 * ("products[1].count"), because a client that is told only "invalid payload"
 * has to guess which input to point at.
 */
final class InvalidRequestPayload extends InvalidArgumentException
{
    private function __construct(private readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function missing(string $field): self
    {
        return new self($field, \sprintf('The field "%s" is required.', $field));
    }

    public static function expected(string $field, string $expected): self
    {
        return new self($field, \sprintf('The field "%s" must be %s.', $field, $expected));
    }

    public static function duplicated(string $field, string $what): self
    {
        return new self($field, \sprintf('The field "%s" lists %s more than once.', $field, $what));
    }

    public function field(): string
    {
        return $this->field;
    }
}
