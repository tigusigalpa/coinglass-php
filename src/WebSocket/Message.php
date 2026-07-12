<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

use JsonException;
use Tigusigalpa\CoinGlass\Collections\CoinGlassCollection;

/**
 * A single decoded message pushed by a Coinglass WebSocket channel.
 *
 * `$data` holds the raw `"data"` payload as decoded from JSON (typically a
 * list of associative arrays); use {@see self::collection()} to hydrate it
 * into a {@see CoinGlassCollection} of {@see \Tigusigalpa\CoinGlass\Dto\CoinGlassDto}
 * records, the same way REST responses are hydrated.
 */
final class Message
{
    /**
     * @param string                                             $channel The channel name, e.g. "liquidation_orders"
     *                                                                    or "futures_ticker@Binance_BTCUSDT".
     * @param list<array<string, mixed>>|array<string, mixed>    $data    The raw "data" payload.
     */
    private function __construct(
        public readonly string $channel,
        public readonly array $data,
    ) {
    }

    /**
     * Parse a raw WebSocket text frame into a Message. Returns null for
     * frames that are not a JSON object with a "channel" field (e.g. a bare
     * "pong" heartbeat reply).
     */
    public static function fromJson(string $json): ?self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['channel']) || !is_string($decoded['channel']) || $decoded['channel'] === '') {
            return null;
        }

        $data = $decoded['data'] ?? [];

        return new self($decoded['channel'], is_array($data) ? $data : []);
    }

    /**
     * Hydrate the `data` payload into a {@see CoinGlassCollection}, matching
     * the DTO/collection hydration used for REST responses.
     */
    public function collection(): CoinGlassCollection
    {
        if ($this->data === []) {
            return new CoinGlassCollection();
        }

        if (array_is_list($this->data)) {
            /** @var list<array<string, mixed>> $data */
            $data = $this->data;

            return CoinGlassCollection::fromArray($data);
        }

        /** @var array<string, mixed> $data */
        $data = $this->data;

        return CoinGlassCollection::fromArray([$data]);
    }
}
