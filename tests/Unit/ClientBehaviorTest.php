<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;
use Tigusigalpa\CoinGlass\Exceptions\ApiException;
use Tigusigalpa\CoinGlass\Exceptions\NotFoundException;
use Tigusigalpa\CoinGlass\Exceptions\RateLimitException;
use Tigusigalpa\CoinGlass\Exceptions\UnauthorizedException;
use Tigusigalpa\CoinGlass\Resources\EtfResource;
use Tigusigalpa\CoinGlass\Resources\FuturesResource;
use Tigusigalpa\CoinGlass\Resources\IndicatorsResource;
use Tigusigalpa\CoinGlass\Resources\OnChainResource;
use Tigusigalpa\CoinGlass\Resources\OptionsResource;
use Tigusigalpa\CoinGlass\Resources\SpotResource;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketClient;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketConfig;

final class ClientBehaviorTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /** @param list<Response> $responses */
    private function makeClient(array $responses, int $retryAttempts = 0): CoinGlassClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new CoinGlassClient(
            new CoinGlassConfig(apiKey: 'test-key', baseUrl: 'https://api.example.test/', retryAttempts: $retryAttempts, retryDelay: 0.0),
            new GuzzleClient(['handler' => $stack]),
        );
    }

    public function testBuildsAnEncodedRequestFiltersNullsAndUnwrapsSuccessfulEnvelopes(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['code' => '0', 'data' => ['ok' => true]], JSON_THROW_ON_ERROR)),
            new Response(204),
            new Response(200, [], json_encode(['data' => 'implicit-success'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['code' => '', 'data' => 'empty-code-success'], JSON_THROW_ON_ERROR)),
        ]);

        self::assertSame(['ok' => true], $client->request('/v4/test', [
            'term' => 'hello world',
            'zero' => 0,
            'false' => false,
            'absent' => null,
        ]));
        self::assertNull($client->request('/empty'));
        self::assertSame('implicit-success', $client->request('/implicit'));
        self::assertSame('empty-code-success', $client->request('/empty-code'));

        /** @var RequestInterface $request */
        $request = $this->history[0]['request'];
        self::assertSame('https://api.example.test/v4/test?term=hello%20world&zero=0&false=0', (string) $request->getUri());
        self::assertSame('test-key', $request->getHeaderLine('CG-API-KEY'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testMapsMalformedAndErrorResponsesToInformativeExceptions(): void
    {
        $client = $this->makeClient([
            new Response(200, [], 'not json'),
            new Response(500, [], '{"code":5001,"msg":"upstream failed"}'),
            new Response(401, [], '{"msg":"wrong key"}'),
            new Response(404, [], '{"message":"missing"}'),
            new Response(429, ['Retry-After' => '17'], '{"message":"slow down"}'),
        ]);

        try {
            $client->request('/malformed');
            self::fail('Expected malformed JSON to fail.');
        } catch (ApiException $exception) {
            self::assertSame(200, $exception->statusCode);
            self::assertSame('not json', $exception->rawBody);
        }

        try {
            $client->request('/server-error');
            self::fail('Expected a server error to fail.');
        } catch (ApiException $exception) {
            self::assertSame(500, $exception->statusCode);
            self::assertSame('5001', $exception->apiCode);
            self::assertSame('upstream failed', $exception->getMessage());
        }

        $this->expectException(UnauthorizedException::class);
        try {
            $client->request('/unauthorized');
        } catch (UnauthorizedException $exception) {
            self::assertSame('wrong key', $exception->getMessage());
            throw $exception;
        }
    }

    public function testMapsNotFoundAndNumericRetryAfterResponses(): void
    {
        $client = $this->makeClient([
            new Response(404, [], '{"message":"missing"}'),
            new Response(429, ['Retry-After' => '17'], '{"message":"slow down"}'),
        ]);

        try {
            $client->request('/missing');
            self::fail('Expected a not-found response to fail.');
        } catch (NotFoundException $exception) {
            self::assertSame('missing', $exception->getMessage());
            self::assertSame(404, $exception->statusCode);
        }

        try {
            $client->request('/rate-limited');
            self::fail('Expected a rate-limit response to fail.');
        } catch (RateLimitException $exception) {
            self::assertSame(17, $exception->retryAfter);
            self::assertSame('slow down', $exception->getMessage());
        }
    }

    public function testWrapsPsrTransportErrors(): void
    {
        $transport = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class('connection refused') extends RuntimeException implements ClientExceptionInterface {
                };
            }
        };
        $client = new CoinGlassClient(new CoinGlassConfig(apiKey: 'test-key'), $transport);

        try {
            $client->request('/transport-error');
            self::fail('Expected a transport failure to be wrapped.');
        } catch (ApiException $exception) {
            self::assertSame(0, $exception->statusCode);
            self::assertSame('Coinglass API request failed: connection refused', $exception->getMessage());
            self::assertInstanceOf(ClientExceptionInterface::class, $exception->getPrevious());
        }
    }

    public function testFactoriesExposeEveryClientResourceAndReadEnvironmentConfiguration(): void
    {
        $client = CoinGlassClient::make('standalone-key', ['base_url' => 'https://api.example.test/']);

        self::assertInstanceOf(FuturesResource::class, $client->futures());
        self::assertInstanceOf(SpotResource::class, $client->spot());
        self::assertInstanceOf(OptionsResource::class, $client->options());
        self::assertInstanceOf(EtfResource::class, $client->etf());
        self::assertInstanceOf(OnChainResource::class, $client->onChain());
        self::assertInstanceOf(IndicatorsResource::class, $client->indicators());
        self::assertInstanceOf(CoinGlassWebSocketClient::class, $client->websocket(['ping_interval' => 1]));

        $keys = [
            'COINGLASS_API_KEY' => 'environment-key',
            'COINGLASS_BASE_URL' => 'https://api.example.test/',
            'COINGLASS_TIMEOUT' => '2.5',
            'COINGLASS_RETRY_ATTEMPTS' => '4',
            'COINGLASS_RETRY_DELAY' => '0.25',
            'COINGLASS_WS_BASE_URL' => 'ws://127.0.0.1:8080/ws/',
            'COINGLASS_WS_CONNECT_TIMEOUT' => '3.5',
            'COINGLASS_WS_PING_INTERVAL' => '4.5',
        ];
        $previous = [];
        foreach ($keys as $key => $value) {
            $previous[$key] = getenv($key);
            putenv("{$key}={$value}");
        }

        try {
            $config = CoinGlassConfig::fromEnv();
            self::assertSame('environment-key', $config->apiKey);
            self::assertSame('https://api.example.test', $config->baseUrl);
            self::assertSame(2.5, $config->timeout);
            self::assertSame(4, $config->retryAttempts);
            self::assertSame(0.25, $config->retryDelay);

            $webSocketConfig = CoinGlassWebSocketConfig::fromEnv();
            self::assertSame('ws://127.0.0.1:8080/ws', $webSocketConfig->baseUrl);
            self::assertSame(3.5, $webSocketConfig->connectTimeout);
            self::assertSame(4.5, $webSocketConfig->pingInterval);
        } finally {
            foreach ($previous as $key => $value) {
                $value === false ? putenv($key) : putenv("{$key}={$value}");
            }
        }
    }

    public function testExceptionFactoryPreservesTheApiErrorContext(): void
    {
        $previous = new RuntimeException('root cause');
        $exception = ApiException::fromResponse(422, ['code' => 1001, 'msg' => 'invalid request'], $previous, 'raw-body');

        self::assertSame('invalid request', $exception->getMessage());
        self::assertSame(422, $exception->statusCode);
        self::assertSame('1001', $exception->apiCode);
        self::assertSame('raw-body', $exception->rawBody);
        self::assertSame($previous, $exception->getPrevious());
    }
}
