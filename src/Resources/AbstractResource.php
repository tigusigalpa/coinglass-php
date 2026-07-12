<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

use Tigusigalpa\CoinGlass\Collections\CoinGlassCollection;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;

/**
 * Base class for all Coinglass resource groups.
 *
 * Provides a shared {@see self::request()} helper that dispatches a GET
 * request to the underlying {@see CoinGlassClient} and hydrates the
 * response's `data` payload into strongly-typed DTOs/collections.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly CoinGlassClient $client)
    {
    }

    /**
     * Execute a GET request and hydrate the response.
     *
     * - A list of associative arrays is hydrated into a {@see CoinGlassCollection}.
     * - A single associative array is hydrated into a single {@see CoinGlassDto}.
     * - Anything else (scalar lists, scalars, null) is returned as-is.
     *
     * @param array<string, mixed> $query
     */
    protected function request(string $path, array $query = []): mixed
    {
        return $this->hydrate($this->client->request($path, $query));
    }

    /**
     * Execute a GET request and return the raw decoded `data` payload,
     * bypassing DTO hydration entirely.
     *
     * @param array<string, mixed> $query
     */
    protected function raw(string $path, array $query = []): mixed
    {
        return $this->client->request($path, $query);
    }

    /**
     * Map a raw `data` payload into DTOs/collections based on its shape.
     */
    private function hydrate(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            if ($data === [] || is_array($data[0] ?? null)) {
                /** @var list<array<string, mixed>> $data */
                return CoinGlassCollection::fromArray($data);
            }

            // Scalar list, e.g. supportedCoins() => ["BTC", "ETH", ...].
            return $data;
        }

        // Associative array: a map of key => list (e.g. supportedExchangePairs)
        // is hydrated per-value; a flat associative record becomes a single DTO.
        $isMapOfLists = true;
        foreach ($data as $value) {
            if (!is_array($value) || !array_is_list($value)) {
                $isMapOfLists = false;
                break;
            }
        }

        if ($isMapOfLists && $data !== []) {
            return array_map(
                fn (array $list): mixed => $this->hydrate($list),
                $data,
            );
        }

        return CoinGlassDto::fromArray($data);
    }
}
