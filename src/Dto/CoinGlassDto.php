<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Dto;

use ArrayAccess;
use LogicException;

/**
 * Generic, read-only DTO wrapping a single Coinglass API response record.
 *
 * Because the Coinglass API v4 spans dozens of heterogeneous endpoints,
 * this DTO exposes the raw decoded payload as an associative array while
 * also allowing convenient property-style access (`$dto->openInterestUsd`)
 * and array access (`$dto['openInterestUsd']`) for any key present in the
 * response. The original payload is always available via {@see self::$raw}.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class CoinGlassDto implements ArrayAccess
{
    /**
     * @param array<string, mixed> $raw The original decoded payload for this record.
     */
    public function __construct(
        public readonly array $raw = [],
    ) {
    }

    /**
     * Hydrate a DTO instance from a decoded JSON associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Magic property access for any key in the raw payload.
     */
    public function __get(string $name): mixed
    {
        return $this->raw[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->raw[$name]);
    }

    /**
     * Get a value by key, with an optional default if the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    /**
     * Check whether a key exists in the raw payload.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->raw);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->raw);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? ($this->raw[$offset] ?? null) : null;
    }

    /** @throws LogicException Always: DTOs are immutable. */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('CoinGlassDto is immutable.');
    }

    /** @throws LogicException Always: DTOs are immutable. */
    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('CoinGlassDto is immutable.');
    }
}
