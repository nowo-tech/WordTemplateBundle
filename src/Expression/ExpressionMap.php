<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use ArrayAccess;
use LogicException;

use function array_key_exists;
use function is_int;

/**
 * Read-only view of an associative context array for expression conditionals, so both
 * {@code client.country} (dot) and {@code client['country']} (bracket) work in a template.
 *
 * Reading a missing key raises {@see UnknownVariableError}; {@code client.zip ?? ''} and
 * {@code client?.zip} use {@see self::__isset()} and never fail. Values are already exposed
 * (scalars, lists, nested maps) by {@see ExpressionVariables}.
 *
 * @implements ArrayAccess<int|string, mixed>
 *
 * @internal
 */
final readonly class ExpressionMap implements ArrayAccess
{
    /**
     * @param array<int|string, mixed> $values exposed values
     * @param string $path variable path used in error messages (e.g. {@code client})
     */
    public function __construct(
        private array $values,
        private string $path,
    ) {
    }

    /**
     * All exposed values (used by expression functions such as {@code length()} or {@code keys()}).
     *
     * @return array<int|string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function __get(string $name): mixed
    {
        return $this->offsetGet($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        throw new LogicException('Expression variables are read-only.');
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new UnknownVariableError($this->path, is_int($offset) ? $offset : (string) $offset);
        }

        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Expression variables are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Expression variables are read-only.');
    }
}
