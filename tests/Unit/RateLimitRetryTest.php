<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;
use Tigusigalpa\CoinGlass\Exceptions\ApiException;
use Tigusigalpa\CoinGlass\Exceptions\NotFoundException;
use Tigusigalpa\CoinGlass\Exceptions\RateLimitException;
use Tigusigalpa\CoinGlass\Exceptions\UnauthorizedException;

final class RateLimitRetryTest extends TestCase
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

    public function testRetriesOnRateLimitAndEventuallySucceeds(): void
    {
        $successBody = json_encode([
            'code' => '0',
            'msg' => 'success',
            'data' => ['underlying' => 'BTC', 'expiry' => 1, 'maxPain' => 42000.0],
        ], JSON_THROW_ON_ERROR);

        $client = $this->makeClient([
            new Response(429, ['Retry-After' => '0'], json_encode(['message' => 'Too many requests'])),
            new Response(429, ['Retry-After' => '0'], json_encode(['message' => 'Too many requests'])),
            new Response(200, [], $successBody),
        ], retryAttempts: 3, retryDelay: 0.0);

        $result = $client->options()->maxPain('BTC');

        self::assertInstanceOf(CoinGlassDto::class, $result);
        self::assertEqualsWithDelta(42000.0, $result->maxPain, 0.001);
        self::assertCount(3, $this->history);
    }

    public function testThrowsRateLimitExceptionAfterExhaustingRetries(): void
    {
        $responses = array_fill(
            0,
            3,
            new Response(429, [], json_encode(['message' => 'Too many requests'])),
        );

        $client = $this->makeClient($responses, retryAttempts: 2, retryDelay: 0.0);

        $this->expectException(RateLimitException::class);

        try {
            $client->futures()->supportedCoins();
        } finally {
            self::assertCount(3, $this->history);
        }
    }

    public function testUnauthorizedResponseThrowsUnauthorizedException(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode(['message' => 'Invalid API key'])),
        ]);

        $this->expectException(UnauthorizedException::class);

        $client->futures()->supportedCoins();
    }

    public function testNotFoundResponseThrowsNotFoundException(): void
    {
        $client = $this->makeClient([
            new Response(404, [], json_encode(['message' => 'Not found'])),
        ]);

        $this->expectException(NotFoundException::class);

        $client->futures()->supportedCoins();
    }

    public function testNonZeroEnvelopeCodeThrowsApiException(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['code' => '30001', 'msg' => 'invalid parameter', 'data' => null])),
        ]);

        $this->expectException(ApiException::class);

        $client->futures()->supportedCoins();
    }

    public function testFractionalRetryDelayIsNotTruncated(): void
    {
        $client = $this->makeClient([
            new Response(429, [], json_encode(['message' => 'Too many requests'])),
            new Response(200, [], json_encode(['code' => '0', 'data' => []])),
        ], retryAttempts: 1, retryDelay: 0.02);

        $startedAt = microtime(true);
        $client->futures()->supportedCoins();

        self::assertGreaterThanOrEqual(0.015, microtime(true) - $startedAt);
    }

    public function testRateLimitExceptionParsesHttpDateAndKeepsRawBody(): void
    {
        $rawBody = '{"message":"Too many requests"}';
        $retryAfter = gmdate('D, d M Y H:i:s \\G\\M\\T', time() + 120);
        $client = $this->makeClient([
            new Response(429, ['Retry-After' => $retryAfter], $rawBody),
        ]);

        try {
            $client->futures()->supportedCoins();
            self::fail('Expected a rate-limit exception.');
        } catch (RateLimitException $e) {
            self::assertSame($rawBody, $e->rawBody);
            self::assertGreaterThanOrEqual(118, $e->retryAfter);
            self::assertLessThanOrEqual(120, $e->retryAfter);
        }
    }

    public function testApiExceptionKeepsRawNonJsonErrorBody(): void
    {
        $client = $this->makeClient([
            new Response(500, [], 'upstream service unavailable'),
        ]);

        try {
            $client->futures()->supportedCoins();
            self::fail('Expected an API exception.');
        } catch (ApiException $e) {
            self::assertSame('upstream service unavailable', $e->rawBody);
        }
    }

    public function testRejectsANonObjectSuccessEnvelope(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '[]'),
        ]);

        try {
            $client->futures()->supportedCoins();
            self::fail('Expected an API exception.');
        } catch (ApiException $e) {
            self::assertSame('[]', $e->rawBody);
        }
    }
}
