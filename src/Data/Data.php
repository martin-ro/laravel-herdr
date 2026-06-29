<?php

namespace Herdr\Data;

use ArrayAccess;
use Herdr\Connection;
use Herdr\Exceptions\HerdrException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;

/**
 * Base class for Herdr objects. It is a thin, permissive wrapper over the raw
 * attribute array returned by the socket API: typed accessors exist for the
 * documented fields, while every other field remains reachable via array
 * access, `get()` or magic property access. This means undocumented or
 * forward-added fields are never silently dropped.
 *
 * Subclasses are "live": when constructed with a {@see Connection} they can
 * also perform actions against the object they describe.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
abstract class Data implements ArrayAccess, Arrayable, JsonSerializable, Stringable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        protected array $attributes = [],
        protected ?Connection $connection = null,
    ) {
    }

    /**
     * The key under which a single object of this type is nested inside a
     * result envelope, e.g. {"type":"pane_info","pane":{...}} → "pane".
     */
    public const INNER_KEY = '';

    /**
     * Candidate keys under which a list of these objects may appear in a
     * result envelope, e.g. {"workspaces":[...]} → ["workspaces"].
     *
     * @var list<string>
     */
    public const LIST_KEYS = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = [], ?Connection $connection = null): static
    {
        return new static($attributes, $connection);
    }

    /**
     * Build a single instance from a result envelope, unwrapping the nested
     * object when present.
     *
     * @param  array<string, mixed>  $result
     */
    public static function fromResult(array $result, ?Connection $connection = null): static
    {
        $key = static::INNER_KEY;

        $attributes = ($key !== '' && isset($result[$key]) && is_array($result[$key]))
            ? $result[$key]
            : $result;

        return new static($attributes, $connection);
    }

    /**
     * Build a collection of instances from a list-bearing result envelope.
     *
     * @param  array<string, mixed>  $result
     * @return Collection<int, static>
     */
    public static function collectionFromResult(array $result, ?Connection $connection = null): Collection
    {
        return collect(static::extractList($result))
            ->map(fn ($item) => new static((array) $item, $connection))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, mixed>
     */
    protected static function extractList(array $result): array
    {
        foreach (static::LIST_KEYS as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                return array_values($result[$key]);
            }
        }

        // Fall back to the first list-shaped value (ignoring the type tag).
        foreach ($result as $key => $value) {
            if ($key === 'type') {
                continue;
            }

            if (is_array($value) && (array_is_list($value) || $value === [])) {
                return array_values($value);
            }
        }

        return [];
    }

    /**
     * Get an attribute using "dot" notation, with a default fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->attributes, $key, $default);
    }

    /**
     * Whether the given attribute is present (and not null).
     */
    public function has(string $key): bool
    {
        return data_get($this->attributes, $key) !== null;
    }

    /**
     * The raw attribute array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function connection(): Connection
    {
        if (! $this->connection instanceof Connection) {
            throw new HerdrException(sprintf(
                '%s has no Herdr connection bound; it cannot perform actions. '
                .'Obtain it through the Herdr facade/manager rather than constructing it directly.',
                static::class,
            ));
        }

        return $this->connection;
    }

    /**
     * Merge fresh attributes into this instance (e.g. after a refresh).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /* ----------------------------------------------------------------------
     | Magic + interface plumbing
     |---------------------------------------------------------------------- */

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        // Allow camelCase access to snake_case fields, e.g. $pane->paneId.
        $snake = Str::snake($name);

        return $this->attributes[$snake] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->attributes)
            || array_key_exists(Str::snake($name), $this->attributes);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->attributes[] = $value;

            return;
        }

        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function __toString(): string
    {
        return (string) json_encode($this->attributes, JSON_UNESCAPED_SLASHES);
    }
}
