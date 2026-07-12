<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\Collections\CoinGlassCollection;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;

final class FuturesResourceTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /**
     * @param list<Response> $responses
     */
    private function makeClient(array $responses, int $retryAttempts = 0, float $retryDelay = 0.0): CoinGlassClient
    {
        $this->history = [];

        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $guzzle = new GuzzleClient(['handler' => $stack]);
        $config = new CoinGlassConfig(
            apiKey: 'test-key',
            retryAttempts: $retryAttempts,
            retryDelay: $retryDelay,
        );

        return new CoinGlassClient($config, $guzzle);
    }

    public function testOpenInterestHistoryHydratesCollectionAndBuildsExpectedRequest(): void
    {
        $body = json_encode([
            'code' => '0',
            'msg' => 'success',
            'data' => [
                ['openInterest' => 1.5, 'openInterestUsd' => 100000.0, 't' => 123456],
                ['openInterest' => 2.5, 'openInterestUsd' => 200000.0, 't' => 123457],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = $this->makeClient([new Response(200, ['Content-Type' => 'application/json'], $body)]);

        $result = $client->futures()->openInterestOhlcHistory('BTC', '1d', 30);

        self::assertInstanceOf(CoinGlassCollection::class, $result);
        self::assertCount(2, $result);
        self::assertInstanceOf(CoinGlassDto::class, $result->first());
        self::assertEqualsWithDelta(100000.0, $result->first()->openInterestUsd, 0.001);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $this->history[0]['request'];
        self::assertSame('/api/futures/openInterest/ohlc-history', $request->getUri()->getPath());
        self::assertSame('test-key', $request->getHeaderLine('CG-API-KEY'));

        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('BTC', $query['symbol']);
        self::assertSame('1d', $query['interval']);
        self::assertSame('30', $query['limit']);
    }

    public function testSupportedCoinsReturnsScalarListUnhydrated(): void
    {
        $body = json_encode([
            'code' => '0',
            'msg' => 'success',
            'data' => ['BTC', 'ETH', 'SOL'],
        ], JSON_THROW_ON_ERROR);

        $client = $this->makeClient([new Response(200, [], $body)]);

        $result = $client->futures()->supportedCoins();

        self::assertSame(['BTC', 'ETH', 'SOL'], $result);
    }

    public function testMaxPainHydratesSingleDto(): void
    {
        $body = json_encode([
            'code' => '0',
            'msg' => 'success',
            'data' => ['underlying' => 'BTC', 'expiry' => 123456, 'maxPain' => 50000.0],
        ], JSON_THROW_ON_ERROR);

        $client = $this->makeClient([new Response(200, [], $body)]);

        $result = $client->options()->maxPain('BTC');

        self::assertInstanceOf(CoinGlassDto::class, $result);
        self::assertEqualsWithDelta(50000.0, $result->maxPain, 0.001);
    }
}
