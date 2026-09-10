<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass Spot endpoint group.
 *
 * @example
 * ```php
 * $markets = $client->spot()->coinsMarkets();
 * $orderbook = $client->spot()->orderbookHistory('BTCUSDT', 'Binance', '1h');
 * ```
 */
final class SpotResource extends AbstractResource
{
    /** Supported spot coins. `GET /api/spot/supported-coins`. */
    public function supportedCoins(): mixed
    {
        return $this->request('/api/spot/supported-coins');
    }

    /** Coins markets. `GET /api/spot/coins-markets`. */
    public function coinsMarkets(?string $symbol = null, ?int $limit = null, ?string $exchange = null): mixed
    {
        return $this->request('/api/spot/coins-markets', [
            'symbol' => $symbol,
            'limit' => $limit,
            'exchange' => $exchange,
        ]);
    }

    /** Pairs markets. `GET /api/spot/pairs-markets`. */
    public function pairsMarkets(?string $symbol = null, ?string $exchange = null, ?int $limit = null): mixed
    {
        return $this->request('/api/spot/pairs-markets', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'limit' => $limit,
        ]);
    }

    /** Price OHLC history. `GET /api/spot/price/history`. */
    public function priceHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/spot/price/history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Orderbook heatmap history. `GET /api/spot/orderbook/history`. */
    public function orderbookHistory(string $symbol, string $exchange, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/spot/orderbook/history', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Taker buy/sell volume history. `GET /api/spot/taker-buy-sell-volume/history`. */
    public function takerBuySellHistory(string $symbol, string $exchange, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/spot/taker-buy-sell-volume/history', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }
}
