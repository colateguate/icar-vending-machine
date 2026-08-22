<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Request;

use App\VendingMachine\Delivery\Http\Error\InvalidRequestPayload;
use App\VendingMachine\Delivery\Http\Error\MalformedJson;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * A decoded request body that can only be read in ways that hold up.
 *
 * Every accessor either returns the type it promises or throws naming the
 * field, so a request DTO reads as a list of what the payload must contain and
 * has nowhere to put an unchecked cast. Nesting keeps the path, which is what
 * turns "invalid payload" into "products[1].count".
 *
 * Decoded into objects rather than associative arrays on purpose. With
 * assoc = true, PHP turns the JSON object {"0": {...}} into the array
 * [0 => ...], which is indistinguishable from the JSON array [{...}] — so a
 * payload of the wrong shape would slip through the one check that is supposed
 * to catch it. Objects and lists stay different things here because JSON says
 * they are.
 */
final readonly class JsonBody
{
    private const MAX_DEPTH = 32;

    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(private array $values, private string $path)
    {
    }

    public static function of(Request $request): self
    {
        try {
            $decoded = json_decode($request->getContent(), false, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw MalformedJson::couldNotBeParsed();
        }

        if (!$decoded instanceof stdClass) {
            throw MalformedJson::notAnObject();
        }

        return new self(get_object_vars($decoded), '');
    }

    public function string(string $field): string
    {
        $value = $this->valueOf($field);

        return \is_string($value)
            ? $value
            : throw InvalidRequestPayload::expected($this->pathTo($field), 'a string');
    }

    /**
     * Counts of things: bottles in a slot, coins in the till. Refused here
     * rather than deeper down, where a negative one breaks an invariant and
     * becomes a 500 — the machine cannot hold minus one bottle, so nothing
     * below has any reason to expect the question.
     *
     * @return int<0, max>
     */
    public function nonNegativeInt(string $field): int
    {
        $value = $this->valueOf($field);

        if (!\is_int($value)) {
            throw InvalidRequestPayload::expected($this->pathTo($field), 'a whole number');
        }

        if ($value < 0) {
            throw InvalidRequestPayload::expected($this->pathTo($field), 'zero or more');
        }

        return $value;
    }

    /**
     * Whether the caller mentioned a field at all.
     *
     * Only worth asking where saying nothing and saying "nothing" are different
     * answers: a service visit that omits the coin acceptor leaves it alone,
     * while one that sends an empty list switches every denomination off.
     */
    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->values);
    }

    /**
     * @return list<string>
     */
    public function stringList(string $field): array
    {
        $value = $this->valueOf($field);
        $path = $this->pathTo($field);

        if (!\is_array($value)) {
            throw InvalidRequestPayload::expected($path, 'a list');
        }

        $items = [];
        foreach ($value as $index => $item) {
            $items[] = \is_string($item)
                ? $item
                : throw InvalidRequestPayload::expected(\sprintf('%s[%d]', $path, $index), 'a string');
        }

        return $items;
    }

    /**
     * @return list<self>
     */
    public function objectList(string $field): array
    {
        $value = $this->valueOf($field);
        $path = $this->pathTo($field);

        if (!\is_array($value)) {
            throw InvalidRequestPayload::expected($path, 'a list');
        }

        $items = [];
        foreach ($value as $index => $item) {
            $itemPath = \sprintf('%s[%d]', $path, $index);

            if (!$item instanceof stdClass) {
                throw InvalidRequestPayload::expected($itemPath, 'an object');
            }

            $items[] = new self(get_object_vars($item), $itemPath);
        }

        return $items;
    }

    private function valueOf(string $field): mixed
    {
        if (!\array_key_exists($field, $this->values)) {
            throw InvalidRequestPayload::missing($this->pathTo($field));
        }

        return $this->values[$field];
    }

    private function pathTo(string $field): string
    {
        return '' === $this->path ? $field : $this->path.'.'.$field;
    }
}
