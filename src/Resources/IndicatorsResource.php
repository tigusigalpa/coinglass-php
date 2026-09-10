<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass market Indicators endpoint group.
 *
 * @example
 * ```php
 * $fearGreed = $client->indicators()->fearGreedHistory(30);
 * ```
 */
final class IndicatorsResource extends AbstractResource
{
    /** Fear & Greed index history. `GET /api/index/fear-greed-history`. */
    public function fearGreedHistory(?int $limit = null): mixed
    {
        return $this->request('/api/index/fear-greed-history', ['limit' => $limit]);
    }

    /** Futures RSI list. `GET /api/futures/rsi/list`. */
    public function rsiList(?string $symbol = null, ?string $interval = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/rsi/list', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Futures basis history. `GET /api/futures/basis/history`. */
    public function basisHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/futures/basis/history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Coinbase premium index. `GET /api/coinbase-premium-index`. */
    public function coinbasePremiumIndex(?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/coinbase-premium-index', [
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Bitcoin rainbow chart. `GET /api/index/bitcoin/rainbow-chart`. */
    public function bitcoinRainbowChart(): mixed
    {
        return $this->request('/api/index/bitcoin/rainbow-chart');
    }

    /** Stock-to-Flow model. `GET /api/index/stock-flow`. */
    public function stockToFlow(): mixed
    {
        return $this->request('/api/index/stock-flow');
    }

    /** Stablecoin market cap history. `GET /api/index/stableCoin-marketCap-history`. */
    public function stablecoinMarketCap(?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/index/stableCoin-marketCap-history', [
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }
}
