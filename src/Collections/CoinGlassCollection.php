<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Collections;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;
use Traversable;

/**
 * Typed, read-only collection of {@see CoinGlassDto} records returned by
 * Coinglass API list endpoints.
 *
 * @implements IteratorAggregate<int, CoinGlassDto>
 * @implements ArrayAccess<int, CoinGlassDto>
 */
final class CoinGlassCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var list<CoinGlassDto> */
    private array $items;

    /**
     * @param list<CoinGlassDto> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Build a collection directly from a list of decoded JSON associative arrays.
     *
     * @param list<array<string, mixed>> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            static fn (array $item): CoinGlassDto => CoinGlassDto::fromArray($item),
            $data,
        ));
    }

    /**
     * @return list<CoinGlassDto>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?CoinGlassDto
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?CoinGlassDto
    {
        return $this->items === [] ? null : $this->items[array_key_last($this->items)];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @param callable(CoinGlassDto): bool $callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_values(array_filter($this->items, $callback)));
    }

    /**
     * @template TMapped
     *
     * @param callable(CoinGlassDto): TMapped $callback
     *
     * @return list<TMapped>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (CoinGlassDto $item): array => $item->toArray(), $this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): CoinGlassDto
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
