<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass Futures endpoint group.
 *
 * @example
 * ```php
 * $oi = $client->futures()->openInterestOhlcHistory('BTC', '1d', 30);
 * $funding = $client->futures()->fundingRateExchangeList('BTC');
 * $liq = $client->futures()->liquidationHistory('BTC', 'BTCUSDT', '1h');
 * ```
 */
final class FuturesResource extends AbstractResource
{
    /** Supported futures coins. `GET /futures/supported-coins`. */
    public function supportedCoins(): mixed
    {
        return $this->request('/futures/supported-coins');
    }

    /** Supported exchange pairs. `GET /api/futures/supported-exchange-pairs`. */
    public function supportedExchangePairs(?string $exchange = null): mixed
    {
        return $this->request('/api/futures/supported-exchange-pairs', ['exchange' => $exchange]);
    }

    /** Futures pair markets. `GET /api/futures/pairs-markets`. */
    public function pairsMarkets(?string $symbol = null, ?string $exchange = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/pairs-markets', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'limit' => $limit,
        ]);
    }

    /** Futures coin markets. `GET /api/futures/coins-markets`. */
    public function coinsMarkets(?string $symbol = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/coins-markets', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }

    /** Price change list. `GET /futures/price-change-list`. */
    public function priceChangeList(): mixed
    {
        return $this->request('/futures/price-change-list');
    }

    /** Price OHLC history. `GET /api/price/ohlc-history`. */
    public function priceOhlcHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/price/ohlc-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Open interest OHLC history. `GET /api/futures/openInterest/ohlc-history`. */
    public function openInterestOhlcHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/futures/openInterest/ohlc-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Aggregated open interest OHLC history. `GET /api/futures/openInterest/ohlc-aggregated-history`. */
    public function openInterestAggregatedHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/futures/openInterest/ohlc-aggregated-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** Open interest by exchange. `GET /api/futures/openInterest/exchange-list`. */
    public function openInterestExchangeList(string $symbol, string $interval, ?int $limit = null, ?string $exchange = null): mixed
    {
        return $this->request('/api/futures/openInterest/exchange-list', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'exchange' => $exchange,
        ]);
    }

    /** Funding rate OHLC history. `GET /api/futures/fundingRate/ohlc-history`. */
    public function fundingRateOhlcHistory(string $symbol, string $interval, ?int $limit = null, ?int $startTime = null, ?int $endTime = null): mixed
    {
        return $this->request('/api/futures/fundingRate/ohlc-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /** OI-weighted funding rate history. `GET /api/futures/fundingRate/oi-weight-ohlc-history`. */
    public function fundingRateOiWeighted(string $symbol, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/fundingRate/oi-weight-ohlc-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Funding rate by exchange. `GET /api/futures/fundingRate/exchange-list`. */
    public function fundingRateExchangeList(string $symbol, ?string $interval = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/fundingRate/exchange-list', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Funding rate arbitrage opportunities. `GET /api/futures/fundingRate/arbitrage`. */
    public function fundingRateArbitrage(?string $symbol = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/fundingRate/arbitrage', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }

    /** Global long/short account ratio history. `GET /api/futures/global-long-short-account-ratio/history`. */
    public function longShortAccountRatioHistory(string $symbol, string $interval, ?int $limit = null, ?string $exchange = null): mixed
    {
        return $this->request('/api/futures/global-long-short-account-ratio/history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'exchange' => $exchange,
        ]);
    }

    /** Top trader long/short account ratio history. `GET /api/futures/top-long-short-account-ratio/history`. */
    public function topLongShortAccountRatio(string $symbol, string $interval, ?int $limit = null, ?string $exchange = null): mixed
    {
        return $this->request('/api/futures/top-long-short-account-ratio/history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'exchange' => $exchange,
        ]);
    }

    /** Pair liquidation history. `GET /api/futures/liquidation/history`. */
    public function liquidationHistory(string $symbol, string $pair, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/liquidation/history', [
            'symbol' => $symbol,
            'pair' => $pair,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Coin liquidation history. `GET /api/futures/liquidation/aggregated-history`. */
    public function liquidationAggregatedHistory(string $symbol, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/liquidation/aggregated-history', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Liquidation coin list. `GET /api/futures/liquidation/coin-list`. */
    public function liquidationCoinList(?string $symbol = null, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/liquidation/coin-list', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }

    /**
     * Liquidation heatmap. `GET /api/futures/liquidation/heatmap/model{1,2,3}`.
     *
     * @param string $model Heatmap model identifier: `"1"`, `"2"`, or `"3"`.
     */
    public function liquidationHeatmap(string $model, string $symbol, string $interval, ?int $limit = null): mixed
    {
        return $this->request("/api/futures/liquidation/heatmap/model{$model}", [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Liquidation map. `GET /api/futures/liquidation/map`. */
    public function liquidationMap(string $symbol, string $pair, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/liquidation/map', [
            'symbol' => $symbol,
            'pair' => $pair,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Orderbook heatmap history. `GET /api/futures/orderbook/history`. */
    public function orderbookHistory(string $symbol, string $exchange, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/orderbook/history', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Large limit orders. `GET /api/futures/orderbook/large-limit-order`. */
    public function orderbookLargeOrders(string $symbol, string $exchange, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/orderbook/large-limit-order', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'limit' => $limit,
        ]);
    }

    /** Taker buy/sell volume history. `GET /api/futures/taker-buy-sell-volume/history`. */
    public function takerBuySellHistory(string $symbol, string $exchange, string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/futures/taker-buy-sell-volume/history', [
            'symbol' => $symbol,
            'exchange' => $exchange,
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Hyperliquid whale alert feed. `GET /api/hyperliquid/whale-alert`. */
    public function whaleBuySellHistory(?string $symbol = null, ?int $limit = null): mixed
    {
        return $this->request('/api/hyperliquid/whale-alert', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }
}
