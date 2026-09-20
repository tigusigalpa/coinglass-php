<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Tigusigalpa\CoinGlass\Collections\CoinGlassCollection;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;
use Tigusigalpa\CoinGlass\Resources\AbstractResource;

final class ResourceContractTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function makeClient(int $responseCount = 60): CoinGlassClient
    {
        $this->history = [];
        $responses = [];

        for ($i = 0; $i < $responseCount; $i++) {
            $responses[] = new Response(200, [], json_encode([
                'code' => '0',
                'data' => ['endpoint' => 'ok'],
            ], JSON_THROW_ON_ERROR));
        }

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new CoinGlassClient(
            new CoinGlassConfig(apiKey: 'test-key'),
            new GuzzleClient(['handler' => $stack]),
        );
    }

    public function testEveryPublicRestEndpointBuildsItsDocumentedRequest(): void
    {
        $client = $this->makeClient();
        $futures = $client->futures();
        $spot = $client->spot();
        $options = $client->options();
        $etf = $client->etf();
        $onChain = $client->onChain();
        $indicators = $client->indicators();

        /** @var list<array{0: object, 1: string, 2: list<mixed>, 3: string, 4: array<string, string>}> $calls */
        $calls = [
            [$futures, 'supportedCoins', [], '/futures/supported-coins', []],
            [$futures, 'supportedExchangePairs', ['Binance'], '/api/futures/supported-exchange-pairs', ['exchange' => 'Binance']],
            [$futures, 'pairsMarkets', ['BTC', 'Binance', 5], '/api/futures/pairs-markets', ['symbol' => 'BTC', 'exchange' => 'Binance', 'limit' => '5']],
            [$futures, 'coinsMarkets', ['BTC', 5, 'Binance,OKX'], '/api/futures/coins-markets', ['symbol' => 'BTC', 'limit' => '5', 'exchanges' => 'Binance,OKX']],
            [$futures, 'priceChangeList', [], '/futures/price-change-list', []],
            [$futures, 'priceOhlcHistory', ['BTC', '1h', 5, 100, 200], '/api/price/ohlc-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'openInterestOhlcHistory', ['BTC', '1h', 5, 100, 200], '/api/futures/openInterest/ohlc-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'openInterestAggregatedHistory', ['BTC', '1h', 5, 100, 200], '/api/futures/openInterest/ohlc-aggregated-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'openInterestExchangeList', ['BTC', '1h', 5, 'Binance', 100, 200], '/api/futures/openInterest/exchange-list', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'exchange' => 'Binance', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'fundingRateOhlcHistory', ['BTC', '1h', 5, 100, 200], '/api/futures/fundingRate/ohlc-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'fundingRateOiWeighted', ['BTC', '1h', 5, 100, 200], '/api/futures/fundingRate/oi-weight-ohlc-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'fundingRateExchangeList', ['BTC', '1h', 5, 'Binance', 100, 200], '/api/futures/fundingRate/exchange-list', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'exchange' => 'Binance', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'fundingRateArbitrage', ['BTC', 5, '1h'], '/api/futures/fundingRate/arbitrage', ['symbol' => 'BTC', 'limit' => '5', 'interval' => '1h']],
            [$futures, 'longShortAccountRatioHistory', ['BTC', '1h', 5, 'Binance', 100, 200], '/api/futures/global-long-short-account-ratio/history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'exchange' => 'Binance', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'topLongShortAccountRatio', ['BTC', '1h', 5, 'Binance', 100, 200], '/api/futures/top-long-short-account-ratio/history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'exchange' => 'Binance', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'liquidationHistory', ['BTC', 'BTCUSDT', '1h', 5, 100, 200], '/api/futures/liquidation/history', ['symbol' => 'BTC', 'pair' => 'BTCUSDT', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'liquidationAggregatedHistory', ['BTC', '1h', 5, 100, 200], '/api/futures/liquidation/aggregated-history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'liquidationCoinList', ['BTC', 5], '/api/futures/liquidation/coin-list', ['symbol' => 'BTC', 'limit' => '5']],
            [$futures, 'liquidationHeatmap', ['1', 'BTC', '1h', 5], '/api/futures/liquidation/heatmap/model1', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5']],
            [$futures, 'liquidationMap', ['BTC', 'BTCUSDT', '1h', 5], '/api/futures/liquidation/map', ['symbol' => 'BTC', 'pair' => 'BTCUSDT', 'interval' => '1h', 'limit' => '5']],
            [$futures, 'orderbookHistory', ['BTC', 'Binance', '1h', 5, 100, 200], '/api/futures/orderbook/history', ['symbol' => 'BTC', 'exchange' => 'Binance', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$futures, 'orderbookLargeOrders', ['BTC', 'Binance', 5, '1h'], '/api/futures/orderbook/large-limit-order', ['symbol' => 'BTC', 'exchange' => 'Binance', 'limit' => '5', 'interval' => '1h']],
            [$futures, 'takerBuySellHistory', ['BTC', 'Binance', '1h', 5], '/api/futures/taker-buy-sell-volume/history', ['symbol' => 'BTC', 'exchange' => 'Binance', 'interval' => '1h', 'limit' => '5']],
            [$futures, 'whaleBuySellHistory', ['BTC', 5, '1h'], '/api/hyperliquid/whale-alert', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5']],
            [$futures, 'whaleAlert', ['BTC', '1h', 5], '/api/hyperliquid/whale-alert', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5']],
            [$spot, 'supportedCoins', [], '/api/spot/supported-coins', []],
            [$spot, 'coinsMarkets', ['BTC', 5, 'Binance'], '/api/spot/coins-markets', ['symbol' => 'BTC', 'limit' => '5', 'exchange' => 'Binance']],
            [$spot, 'pairsMarkets', ['BTC', 'Binance', 5], '/api/spot/pairs-markets', ['symbol' => 'BTC', 'exchange' => 'Binance', 'limit' => '5']],
            [$spot, 'priceHistory', ['BTC', '1h', 5, 100, 200], '/api/spot/price/history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$spot, 'orderbookHistory', ['BTC', 'Binance', '1h', 5, 100, 200], '/api/spot/orderbook/history', ['symbol' => 'BTC', 'exchange' => 'Binance', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$spot, 'takerBuySellHistory', ['BTC', 'Binance', '1h', 5], '/api/spot/taker-buy-sell-volume/history', ['symbol' => 'BTC', 'exchange' => 'Binance', 'interval' => '1h', 'limit' => '5']],
            [$options, 'maxPain', ['BTC', 123, '1h'], '/api/option/max-pain', ['underlying' => 'BTC', 'expiry' => '123', 'interval' => '1h']],
            [$options, 'info', ['BTC', 123, '1h'], '/api/option/info', ['underlying' => 'BTC', 'expiry' => '123', 'interval' => '1h']],
            [$options, 'exchangeOiHistory', ['1h', 5, 100, 200], '/api/option/exchange-oi-history', ['interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$options, 'exchangeVolHistory', ['1h', 5, 100, 200], '/api/option/exchange-vol-history', ['interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$etf, 'bitcoinList', [], '/api/etf/bitcoin/list', []],
            [$etf, 'bitcoinFlowHistory', ['1d', 5], '/api/etf/bitcoin/flow-history', ['interval' => '1d', 'limit' => '5']],
            [$etf, 'bitcoinNetAssetsHistory', ['1d', 5], '/api/etf/bitcoin/net-assets/history', ['interval' => '1d', 'limit' => '5']],
            [$etf, 'ethereumList', [], '/api/etf/ethereum/list', []],
            [$etf, 'ethereumFlowHistory', ['1d', 5], '/api/etf/ethereum/flow-history', ['interval' => '1d', 'limit' => '5']],
            [$etf, 'grayscaleHoldings', [], '/api/grayscale/holdings-list', []],
            [$onChain, 'exchangeAssets', [], '/api/exchange/assets', []],
            [$onChain, 'exchangeBalanceList', ['BTC', 'Binance'], '/api/exchange/balance/list', ['symbol' => 'BTC', 'exchange' => 'Binance']],
            [$onChain, 'exchangeOnChainTransfers', ['BTC', 5], '/api/exchange/chain/tx/list', ['symbol' => 'BTC', 'limit' => '5']],
            [$indicators, 'fearGreedHistory', [5], '/api/index/fear-greed-history', ['limit' => '5']],
            [$indicators, 'rsiList', ['BTC', '1h', 5], '/api/futures/rsi/list', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5']],
            [$indicators, 'basisHistory', ['BTC', '1h', 5, 100, 200], '/api/futures/basis/history', ['symbol' => 'BTC', 'interval' => '1h', 'limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$indicators, 'coinbasePremiumIndex', [5, 100, 200], '/api/coinbase-premium-index', ['limit' => '5', 'startTime' => '100', 'endTime' => '200']],
            [$indicators, 'bitcoinRainbowChart', [], '/api/index/bitcoin/rainbow-chart', []],
            [$indicators, 'stockToFlow', [], '/api/index/stock-flow', []],
            [$indicators, 'stablecoinMarketCap', [5, 100, 200], '/api/index/stableCoin-marketCap-history', ['limit' => '5', 'startTime' => '100', 'endTime' => '200']],
        ];

        foreach ($calls as $index => [$resource, $method, $arguments, $path, $expectedQuery]) {
            self::assertInstanceOf(CoinGlassDto::class, $resource->{$method}(...$arguments));

            /** @var RequestInterface $request */
            $request = $this->history[$index]['request'];
            self::assertSame($path, $request->getUri()->getPath(), $method);

            parse_str($request->getUri()->getQuery(), $query);
            self::assertSame($expectedQuery, $query, $method);
        }
    }

    public function testAbstractResourceHydratesEveryPayloadShapeAndCanReturnRawData(): void
    {
        $this->history = [];
        $data = [
            ['symbol' => 'BTC'],
            [['symbol' => 'BTC']],
            ['BTC', 'ETH'],
            ['BTC' => [['symbol' => 'BTC']]],
            [],
            'scalar',
            null,
            ['symbol' => 'BTC'],
        ];
        $responses = array_map(
            static fn (mixed $payload): Response => new Response(200, [], json_encode(['code' => '0', 'data' => $payload], JSON_THROW_ON_ERROR)),
            $data,
        );
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $client = new CoinGlassClient(new CoinGlassConfig(apiKey: 'test-key'), new GuzzleClient(['handler' => $stack]));
        $resource = new ExposedResource($client);

        self::assertInstanceOf(CoinGlassDto::class, $resource->hydrated('/record'));
        self::assertInstanceOf(CoinGlassCollection::class, $resource->hydrated('/list'));
        self::assertSame(['BTC', 'ETH'], $resource->hydrated('/scalar-list'));

        $map = $resource->hydrated('/map-of-lists');
        self::assertIsArray($map);
        self::assertInstanceOf(CoinGlassCollection::class, $map['BTC']);

        self::assertInstanceOf(CoinGlassCollection::class, $resource->hydrated('/empty-list'));
        self::assertSame('scalar', $resource->hydrated('/scalar'));
        self::assertNull($resource->hydrated('/null'));
        self::assertSame(['symbol' => 'BTC'], $resource->rawPayload('/raw'));
    }
}

final class ExposedResource extends AbstractResource
{
    public function hydrated(string $path): mixed
    {
        return $this->request($path);
    }

    public function rawPayload(string $path): mixed
    {
        return $this->raw($path);
    }
}
