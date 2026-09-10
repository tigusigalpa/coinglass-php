# Coinglass PHP/Laravel Client/SDK/Library

![CoinGlass PHP SDK](https://i.postimg.cc/tJgkghDn/coinglass-php-banner.jpg)

[![CI](https://github.com/tigusigalpa/coinglass-php/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/coinglass-php/actions/workflows/ci.yml)
[![Tests](https://github.com/tigusigalpa/coinglass-php/actions/workflows/test.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/coinglass-php/actions/workflows/test.yml)
[![Coverage](https://github.com/tigusigalpa/coinglass-php/actions/workflows/coverage.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/coinglass-php/actions/workflows/coverage.yml)
[![CodeQL Actions](https://github.com/tigusigalpa/coinglass-php/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/coinglass-php/actions/workflows/codeql.yml)
[![Codecov](https://codecov.io/gh/tigusigalpa/coinglass-php/graph/badge.svg)](https://codecov.io/gh/tigusigalpa/coinglass-php)
[![Latest Stable Version](https://poser.pugx.org/tigusigalpa/coinglass-php/v)](https://packagist.org/packages/tigusigalpa/coinglass-php)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10--13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

> A PHP client for the Coinglass API v4, with first-class Laravel support.

`coinglass-php` is a framework-agnostic PHP SDK for
the [Coinglass API v4](https://docs.coinglass.com/reference/getting-started-with-your-api). I put it
together while building a liquidation dashboard and got tired of hand-rolling Guzzle calls and juggling raw arrays,
so it wraps the Futures, Spot, Options, ETF, On-Chain, and Indicator endpoints — plus the real-time WebSocket
streams — in a typed, PSR-18-friendly client that drops straight into Laravel if you need it, or works standalone
if you don't.

## Features

- Covers all six endpoint groups: Futures, Spot, Options, ETF, On-Chain, and Indicators.
- Ships a real-time WebSocket client for liquidation orders, spot/futures trades, and futures ticker snapshots —
  no extra WebSocket library needed, it's built on plain PHP streams.
- Framework-agnostic: use it standalone in any PHP 8.1+ project, or drop it straight into Laravel.
- PSR-18 swappable HTTP client — Guzzle by default, bring your own if you'd rather.
- Retries rate-limited (429) requests with exponential backoff, honoring `Retry-After` when Coinglass sends one.
- Hydrates every payload into a `CoinGlassDto` or `CoinGlassCollection`, with the original raw payload always
  within reach.
- A small, purposeful exception hierarchy — `UnauthorizedException`, `NotFoundException`, `RateLimitException`,
  and a generic `ApiException` for everything else — instead of one catch-all.
- Laravel-ready out of the box: publishable config, a bound singleton client, dependency injection, and a
  `CoinGlass` facade.

## Requirements

| Requirement        | Version                                                        |
|--------------------|----------------------------------------------------------------|
| PHP                | 8.1+                                                           |
| Laravel (optional) | 10.x, 11.x, 12.x, 13.x                                         |
| HTTP client        | Guzzle `^7.15.5` (default, PSR-18 swappable)                   |
| WebSocket API      | `ext-openssl` (for `wss://`; no extra Composer package needed) |
| Coinglass API key  | [Get one here](https://coinglass.com)                          |

## Installation

```bash
composer require tigusigalpa/coinglass-php
```

### Local development path

Working against a local checkout? Point Composer at it with a path repository in your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/coinglass-php"
        }
    ]
}
```

## Configuration

### Standalone

```php
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;

// Simple initialization
$client = CoinGlassClient::make('YOUR_API_KEY');

// Full configuration
$config = new CoinGlassConfig(
    apiKey: 'YOUR_API_KEY',
    baseUrl: 'https://open-api-v4.coinglass.com',
    timeout: 15.0,
    retryAttempts: 3,
    retryDelay: 1.0,
);
$client = new CoinGlassClient($config);

// From environment variables (COINGLASS_API_KEY, COINGLASS_BASE_URL, ...)
$client = new CoinGlassClient(CoinGlassConfig::fromEnv());
```

### Laravel

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=coinglass-config
```

```env
COINGLASS_API_KEY=your-api-key
COINGLASS_BASE_URL=https://open-api-v4.coinglass.com
COINGLASS_TIMEOUT=15
COINGLASS_RETRY_ATTEMPTS=3
COINGLASS_RETRY_DELAY=1
```

Via the facade:

```php
use Tigusigalpa\CoinGlass\Laravel\Facades\CoinGlass;

$oi = CoinGlass::futures()->openInterestOhlcHistory('BTC', '1d', 30);
```

Via dependency injection:

```php
use Tigusigalpa\CoinGlass\CoinGlassClient;

class MarketController
{
    public function __construct(private readonly CoinGlassClient $coinGlass) {}

    public function index()
    {
        return $this->coinGlass->etf()->bitcoinFlowHistory('1w', 24);
    }
}
```

## Quick Start

```php
use Tigusigalpa\CoinGlass\CoinGlassClient;

$client = CoinGlassClient::make('YOUR_API_KEY');

// Futures
$oi = $client->futures()->openInterestOhlcHistory('BTC', '1d', 30);
foreach ($oi as $point) {
    echo "{$point->t}: \${$point->openInterestUsd}\n";
}

$funding = $client->futures()->fundingRateExchangeList('BTC');
$liq = $client->futures()->liquidationHistory('BTC', 'BTCUSDT', '1h');

// Spot
$markets = $client->spot()->coinsMarkets();
$orderbook = $client->spot()->orderbookHistory('BTCUSDT', 'Binance', '1h');

// ETF
$flows = $client->etf()->bitcoinFlowHistory('1w', 24);

// Indicators
$fearGreed = $client->indicators()->fearGreedHistory(30);
```

Record payloads come back as a `Tigusigalpa\CoinGlass\Dto\CoinGlassDto` (single record) or a
`Tigusigalpa\CoinGlass\Collections\CoinGlassCollection` (list of records); scalar lists and scalar values stay raw.
Reach for a record field however feels natural —
property syntax (`$dto->openInterestUsd`), array syntax (`$dto['openInterestUsd']`), or `$dto->get('openInterestUsd')` —
and if you ever need the untouched payload, it's right there via `$dto->raw` / `$dto->toArray()`. DTOs and collections
are immutable.

## Full API Reference

Every method maps to a single Coinglass endpoint and returns its `data` payload, hydrating record-shaped responses.
Optional parameters are marked with `?` below.

### Futures — `$client->futures()`

| Method                                                                          | Endpoint                                                   |
|---------------------------------------------------------------------------------|------------------------------------------------------------|
| `supportedCoins()`                                                              | `GET /futures/supported-coins`                             |
| `supportedExchangePairs(?exchange)`                                             | `GET /api/futures/supported-exchange-pairs`                |
| `pairsMarkets(?symbol, ?exchange, ?limit)`                                      | `GET /api/futures/pairs-markets`                           |
| `coinsMarkets(?symbol, ?limit, ?exchanges)`                                     | `GET /api/futures/coins-markets`                           |
| `priceChangeList()`                                                             | `GET /futures/price-change-list`                           |
| `priceOhlcHistory(symbol, interval, ?limit, ?startTime, ?endTime)`              | `GET /api/price/ohlc-history`                              |
| `openInterestOhlcHistory(symbol, interval, ?limit, ?startTime, ?endTime)`       | `GET /api/futures/openInterest/ohlc-history`               |
| `openInterestAggregatedHistory(symbol, interval, ?limit, ?startTime, ?endTime)` | `GET /api/futures/openInterest/ohlc-aggregated-history`    |
| `openInterestExchangeList(symbol, interval, ?limit, ?exchange, ?startTime, ?endTime)` | `GET /api/futures/openInterest/exchange-list`        |
| `fundingRateOhlcHistory(symbol, interval, ?limit, ?startTime, ?endTime)`        | `GET /api/futures/fundingRate/ohlc-history`                |
| `fundingRateOiWeighted(symbol, interval, ?limit, ?startTime, ?endTime)`         | `GET /api/futures/fundingRate/oi-weight-ohlc-history`      |
| `fundingRateExchangeList(symbol, ?interval, ?limit, ?exchange, ?startTime, ?endTime)` | `GET /api/futures/fundingRate/exchange-list`        |
| `fundingRateArbitrage(?symbol, ?limit, ?interval)`                              | `GET /api/futures/fundingRate/arbitrage`                   |
| `longShortAccountRatioHistory(symbol, interval, ?limit, ?exchange, ?startTime, ?endTime)` | `GET /api/futures/global-long-short-account-ratio/history` |
| `topLongShortAccountRatio(symbol, interval, ?limit, ?exchange, ?startTime, ?endTime)` | `GET /api/futures/top-long-short-account-ratio/history` |
| `liquidationHistory(symbol, pair, interval, ?limit, ?startTime, ?endTime)`      | `GET /api/futures/liquidation/history`                     |
| `liquidationAggregatedHistory(symbol, interval, ?limit, ?startTime, ?endTime)`  | `GET /api/futures/liquidation/aggregated-history`          |
| `liquidationCoinList(?symbol, ?limit)`                                          | `GET /api/futures/liquidation/coin-list`                   |
| `liquidationHeatmap(model, symbol, interval, ?limit)`                           | `GET /api/futures/liquidation/heatmap/model{1,2,3}`        |
| `liquidationMap(symbol, pair, interval, ?limit)`                                | `GET /api/futures/liquidation/map`                         |
| `orderbookHistory(symbol, exchange, interval, ?limit, ?startTime, ?endTime)`    | `GET /api/futures/orderbook/history`                       |
| `orderbookLargeOrders(symbol, exchange, ?limit, ?interval)`                     | `GET /api/futures/orderbook/large-limit-order`             |
| `takerBuySellHistory(symbol, exchange, interval, ?limit)`                       | `GET /api/futures/taker-buy-sell-volume/history`           |
| `whaleAlert(?symbol, ?interval, ?limit)`                                        | `GET /api/hyperliquid/whale-alert`                         |
| `whaleBuySellHistory(?symbol, ?limit, ?interval)`                               | Alias for `whaleAlert()`                                   |

### Spot — `$client->spot()`

| Method                                                         | Endpoint                                      |
|----------------------------------------------------------------|-----------------------------------------------|
| `supportedCoins()`                                             | `GET /api/spot/supported-coins`               |
| `coinsMarkets(?symbol, ?limit, ?exchange)`                     | `GET /api/spot/coins-markets`                 |
| `pairsMarkets(?symbol, ?exchange, ?limit)`                     | `GET /api/spot/pairs-markets`                 |
| `priceHistory(symbol, interval, ?limit, ?startTime, ?endTime)` | `GET /api/spot/price/history`                 |
| `orderbookHistory(symbol, exchange, interval, ?limit, ?startTime, ?endTime)` | `GET /api/spot/orderbook/history`    |
| `takerBuySellHistory(symbol, exchange, interval, ?limit)`      | `GET /api/spot/taker-buy-sell-volume/history` |

### Options — `$client->options()`

| Method                                 | Endpoint                               |
|----------------------------------------|----------------------------------------|
| `maxPain(underlying, ?expiry, ?interval)`         | `GET /api/option/max-pain`             |
| `info(underlying, ?expiry, ?interval)`            | `GET /api/option/info`                 |
| `exchangeOiHistory(interval, ?limit, ?startTime, ?endTime)`  | `GET /api/option/exchange-oi-history`  |
| `exchangeVolHistory(interval, ?limit, ?startTime, ?endTime)` | `GET /api/option/exchange-vol-history` |

### ETF — `$client->etf()`

| Method                                      | Endpoint                                  |
|---------------------------------------------|-------------------------------------------|
| `bitcoinList()`                             | `GET /api/etf/bitcoin/list`               |
| `bitcoinFlowHistory(interval, ?limit)`      | `GET /api/etf/bitcoin/flow-history`       |
| `bitcoinNetAssetsHistory(interval, ?limit)` | `GET /api/etf/bitcoin/net-assets/history` |
| `ethereumList()`                            | `GET /api/etf/ethereum/list`              |
| `ethereumFlowHistory(interval, ?limit)`     | `GET /api/etf/ethereum/flow-history`      |
| `grayscaleHoldings()`                       | `GET /api/grayscale/holdings-list`        |

### On-Chain — `$client->onChain()`

| Method                                      | Endpoint                          |
|---------------------------------------------|-----------------------------------|
| `exchangeAssets()`                          | `GET /api/exchange/assets`        |
| `exchangeBalanceList(?symbol, ?exchange)`   | `GET /api/exchange/balance/list`  |
| `exchangeOnChainTransfers(?symbol, ?limit)` | `GET /api/exchange/chain/tx/list` |

### Indicators — `$client->indicators()`

| Method                                   | Endpoint                                      |
|------------------------------------------|-----------------------------------------------|
| `fearGreedHistory(?limit)`               | `GET /api/index/fear-greed-history`           |
| `rsiList(?symbol, ?interval, ?limit)`    | `GET /api/futures/rsi/list`                   |
| `basisHistory(symbol, interval, ?limit, ?startTime, ?endTime)` | `GET /api/futures/basis/history`           |
| `coinbasePremiumIndex(?limit, ?startTime, ?endTime)`           | `GET /api/coinbase-premium-index`          |
| `bitcoinRainbowChart()`                  | `GET /api/index/bitcoin/rainbow-chart`        |
| `stockToFlow()`                          | `GET /api/index/stock-flow`                   |
| `stablecoinMarketCap(?limit, ?startTime, ?endTime)`            | `GET /api/index/stableCoin-marketCap-history` |

## WebSocket API

The REST client covers historical data; for anything real-time — liquidation orders, spot and futures trades,
futures ticker snapshots — there's a small WebSocket client too. It talks
[Coinglass's WebSocket API](https://docs.coinglass.com/reference/ws-getting-started) directly over PHP streams
(`stream_socket_client`), implementing just enough of the protocol to get the job done, so you don't need to pull
in a separate WebSocket library.

```php
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\WebSocket\Channels;
use Tigusigalpa\CoinGlass\WebSocket\Message;

$client = CoinGlassClient::make('YOUR_API_KEY');
$stream = $client->websocket()->connect();

$stream->subscribe(
    Channels::liquidationOrders(),
    Channels::futuresTicker('Binance', 'BTCUSDT'),
);

$stream->listen(function (Message $message) {
    if ($message->channel === Channels::liquidationOrders()) {
        foreach ($message->collection() as $order) {
            echo "{$order->exchange} {$order->symbol} liquidated \${$order->volume_usd}\n";
        }
    }
});
```

A single connection carries every subscription; `subscribe()`/`unsubscribe()` accept any number of channel names,
and the `"ping"` heartbeat Coinglass expects every 20 seconds is sent automatically. `listen()` blocks forever,
which fits a long-running CLI worker or queued job; if you need finer control (e.g. to integrate with an existing
event loop), call `$stream->read($timeoutSeconds)` yourself to pull one message at a time.

### Channel helpers

| Helper                                                       | Channel                                             | Docs                                                                                     |
|--------------------------------------------------------------|-----------------------------------------------------|------------------------------------------------------------------------------------------|
| `Channels::liquidationOrders()`                              | `liquidation_orders`                                | [Liquidation Order](https://docs.coinglass.com/reference/ws-liquidation-order)           |
| `Channels::spotTrades($exchange, $symbol, $minVolumeUsd)`    | `spot_trades@{exchange}_{symbol}@{minVolumeUsd}`    | [Spot Trade Order](https://docs.coinglass.com/reference/websocket_spot_trades)           |
| `Channels::futuresTrades($exchange, $symbol, $minVolumeUsd)` | `futures_trades@{exchange}_{symbol}@{minVolumeUsd}` | [Futures Trade Order](https://docs.coinglass.com/reference/websocket_futures_trades)     |
| `Channels::futuresTicker($exchange, $symbol)`                | `futures_ticker@{exchange}_{symbol}`                | [Futures Ticker Snapshot](https://docs.coinglass.com/reference/websocket_futures_ticker) |

Every message's `data` payload can be hydrated with `$message->collection()`, returning the same
`CoinGlassCollection` of `CoinGlassDto` records used throughout the REST client — access fields with
`$order->volume_usd`, `$order['volume_usd']`, or `$order->get('volume_usd')`.

### Laravel

```php
use Tigusigalpa\CoinGlass\Laravel\Facades\CoinGlass;

$stream = CoinGlass::websocket()->connect();
```

Publish the config (`php artisan vendor:publish --tag=coinglass-config`) to override the WebSocket endpoint via
`COINGLASS_WS_BASE_URL`, `COINGLASS_WS_CONNECT_TIMEOUT`, and `COINGLASS_WS_PING_INTERVAL`.

### Errors

Connection, handshake, and read/write failures throw `Tigusigalpa\CoinGlass\Exceptions\WebSocketException` (a
`CoinGlassException`), so it's caught by the same `catch (CoinGlassException $e)` you might already have.

## Error handling

Nothing fails silently. The common cases each get their own exception, so you can catch exactly what you care about
and let the rest bubble up:

```php
use Tigusigalpa\CoinGlass\Exceptions\ApiException;
use Tigusigalpa\CoinGlass\Exceptions\NotFoundException;
use Tigusigalpa\CoinGlass\Exceptions\RateLimitException;
use Tigusigalpa\CoinGlass\Exceptions\UnauthorizedException;

try {
    $oi = $client->futures()->openInterestOhlcHistory('BTC', '1d', 30);
} catch (UnauthorizedException $e) {
    // Invalid or missing API key
} catch (RateLimitException $e) {
    // Retries exhausted; $e->retryAfter holds the Retry-After value, if any
} catch (NotFoundException $e) {
    // Endpoint/resource not found
} catch (ApiException $e) {
    // Any other non-2xx response or non-zero envelope code
    // $e->statusCode, $e->apiCode, $e->responseBody, $e->rawBody are all available
}
```

## Retry behavior

Coinglass rate-limits aggressively on some plans, so on HTTP `429` the client reads the `Retry-After` header if
present, or falls back to exponential backoff (`retryDelay * 2^attempt`), retrying up to `retryAttempts` times
before giving up and throwing `RateLimitException`:

```php
$config = new CoinGlassConfig(
    apiKey: 'YOUR_API_KEY',
    retryAttempts: 3,
    retryDelay: 1.0, // 1s, then 2s, then 4s
);
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite leans on Guzzle's `MockHandler` for the HTTP-layer unit tests and Orchestra Testbench for the Laravel
integration tests, so it runs without ever touching the real Coinglass API.
The same lint and test checks run on PHP 8.1–8.4 for every push and pull request.

## License

MIT © [Igor Sazonov](https://github.com/tigusigalpa)
