<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

use Fantasy\Sdk\Exceptions\UnexpectedPayloadException;
use Saloon\Http\Response;

/**
 * Typed reader over one decoded JSON object.
 *
 * The API is loose about scalar types -- `count` arrives as an integer while
 * `season`, `week` and every `rank` arrive as numeric strings -- so reading
 * through here keeps the coercion in one place instead of scattered across
 * every DTO.
 */
final readonly class Payload
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    private function __construct(private array $values) {}

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function of(array $values): self
    {
        return new self($values);
    }

    /**
     * Decodes the raw body rather than calling Response::json(), whose phpdoc
     * promises an array -- a promise json_decode does not actually keep for a
     * scalar body, so the guard below would be unverifiable.
     */
    public static function fromResponse(Response $response): self
    {
        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded)) {
            throw UnexpectedPayloadException::notAnObject();
        }

        return new self($decoded);
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /**
     * The object's own keys, for payloads that use IDs as keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->values));
    }

    public function string(string $key): string
    {
        return $this->nullableString($key)
            ?? throw UnexpectedPayloadException::expected('a string', $key, $this->values[$key] ?? null);
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key)
            ?? throw UnexpectedPayloadException::expected('an integer', $key, $this->values[$key] ?? null);
    }

    /**
     * Numeric strings count, since the API returns ranks and seasons as strings.
     */
    public function nullableInt(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Numeric strings count, since probabilities arrive as strings. Note "0" is
     * a real value -- no chance of playing -- and must not collapse to null.
     */
    public function nullableFloat(string $key): ?float
    {
        $value = $this->values[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    public function bool(string $key): bool
    {
        return ($this->values[$key] ?? null) === true;
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw UnexpectedPayloadException::expected('a list of strings', $key, $item);
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @return list<int>
     */
    public function ints(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $ints = [];

        foreach ($value as $item) {
            if (! is_int($item)) {
                throw UnexpectedPayloadException::expected('a list of integers', $key, $item);
            }

            $ints[] = $item;
        }

        return $ints;
    }

    /**
     * A JSON array of objects.
     *
     * @return list<self>
     */
    public function objects(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            throw UnexpectedPayloadException::expected('an array', $key, $value);
        }

        $objects = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw UnexpectedPayloadException::expected('an array of objects', $key, $item);
            }

            $objects[] = new self($item);
        }

        return $objects;
    }

    /**
     * A JSON object used as a lookup, e.g. player ID to player details.
     *
     * Keys stay `array-key`, not `string`: PHP silently casts a numeric string
     * key like "17240" to an int, so a `array<string, ...>` claim would be a lie.
     *
     * @return array<array-key, self>
     */
    public function objectMap(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            throw UnexpectedPayloadException::expected('an object', $key, $value);
        }

        $map = [];

        foreach ($value as $mapKey => $item) {
            if (! is_array($item)) {
                throw UnexpectedPayloadException::expected('an object of objects', $key, $item);
            }

            $map[$mapKey] = new self($item);
        }

        return $map;
    }
}
