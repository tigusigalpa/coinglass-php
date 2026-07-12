<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Tigusigalpa\CoinGlass\Exceptions\ApiException;
use Tigusigalpa\CoinGlass\Exceptions\CoinGlassException;
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

/**
 * Framework-agnostic HTTP client for the Coinglass API v4.
 *
 * Handles request signing (the `CG-API-KEY` header), JSON encoding/decoding
 * of the `{"code","msg","data"}` response envelope, automatic
 * exponential-backoff retries on rate limiting, and mapping of non-2xx
 * responses (or non-zero envelope codes) to the SDK's exception hierarchy.
 *
 * Any PSR-18 compatible HTTP client may be injected via the constructor;
 * Guzzle is used by default.
 */
final class CoinGlassClient
{
    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private UriFactoryInterface $uriFactory;

    /**
     * @param CoinGlassConfig              $config         SDK configuration (API key, base URL, retry policy, etc.).
     * @param ClientInterface|null         $httpClient     Any PSR-18 compatible HTTP client. Defaults to Guzzle.
     * @param RequestFactoryInterface|null $requestFactory PSR-17 request factory. Defaults to Guzzle's HttpFactory.
     * @param StreamFactoryInterface|null  $streamFactory  PSR-17 stream factory. Defaults to Guzzle's HttpFactory.
     * @param UriFactoryInterface|null     $uriFactory     PSR-17 URI factory. Defaults to Guzzle's HttpFactory.
     */
    public function __construct(
        private readonly CoinGlassConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UriFactoryInterface $uriFactory = null,
    ) {
        $factory = new HttpFactory();

        $this->httpClient = $httpClient ?? new GuzzleClient(['timeout' => $config->timeout]);
        $this->requestFactory = $requestFactory ?? $factory;
        $this->streamFactory = $streamFactory ?? $factory;
        $this->uriFactory = $uriFactory ?? $factory;
    }

    /**
     * Convenience factory for standalone (non-Laravel) usage.
     *
     * @param string               $apiKey  Coinglass API v4 key.
     * @param array<string, mixed> $options Optional overrides: base_url, timeout, retry_attempts, retry_delay.
     */
    public static function make(string $apiKey, array $options = []): self
    {
        return new self(CoinGlassConfig::fromArray(['api_key' => $apiKey] + $options));
    }

    /** Access the Futures resource group. */
    public function futures(): FuturesResource
    {
        return new FuturesResource($this);
    }

    /** Access the Spot resource group. */
    public function spot(): SpotResource
    {
        return new SpotResource($this);
    }

    /** Access the Options resource group. */
    public function options(): OptionsResource
    {
        return new OptionsResource($this);
    }

    /** Access the ETF resource group. */
    public function etf(): EtfResource
    {
        return new EtfResource($this);
    }

    /** Access the On-Chain resource group. */
    public function onChain(): OnChainResource
    {
        return new OnChainResource($this);
    }

    /** Access the Indicators resource group. */
    public function indicators(): IndicatorsResource
    {
        return new IndicatorsResource($this);
    }

    /**
     * Access the real-time WebSocket API (liquidation orders, spot/futures
     * trades, futures ticker snapshots). See {@see CoinGlassWebSocketClient::connect()}.
     *
     * @param array<string, mixed> $options Optional overrides: base_url, connect_timeout, ping_interval.
     */
    public function websocket(array $options = []): CoinGlassWebSocketClient
    {
        return CoinGlassWebSocketClient::make($this->config->apiKey, $options);
    }

    /**
     * Send a GET request to the Coinglass API and return the decoded `data` payload.
     *
     * Automatically retries with exponential backoff when a 429 response is
     * received, up to `retry_attempts` times, before throwing {@see RateLimitException}.
     *
     * @param string               $path  API path, e.g. `/api/futures/openInterest/ohlc-history`.
     * @param array<string, mixed> $query Query string parameters.
     *
     * @throws CoinGlassException
     */
    public function request(string $path, array $query = []): mixed
    {
        $attempt = 0;
        $maxAttempts = max(0, $this->config->retryAttempts);

        while (true) {
            try {
                return $this->send($path, $query);
            } catch (RateLimitException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $delay = $e->retryAfter ?? (int) ($this->config->retryDelay * (2 ** $attempt));
                if ($delay > 0) {
                    usleep((int) ($delay * 1_000_000));
                }

                $attempt++;
            }
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function send(string $path, array $query): mixed
    {
        $uri = $this->uriFactory
            ->createUri(rtrim($this->config->baseUrl, '/') . '/' . ltrim($path, '/'));

        $filteredQuery = array_filter($query, static fn ($value) => $value !== null);
        if ($filteredQuery !== []) {
            $uri = $uri->withQuery(http_build_query($filteredQuery, '', '&', PHP_QUERY_RFC3986));
        }

        $request = $this->requestFactory
            ->createRequest('GET', $uri)
            ->withHeader('CG-API-KEY', $this->config->apiKey)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException('Coinglass API request failed: ' . $e->getMessage(), 0, [], null, $e);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = (string) $response->getBody();

        $body = [];
        if ($rawBody !== '') {
            try {
                /** @var array<string, mixed> $body */
                $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                if ($statusCode >= 200 && $statusCode < 300) {
                    throw new ApiException('Failed to decode Coinglass API response: ' . $e->getMessage(), $statusCode, [], null, $e);
                }
                $body = ['message' => $rawBody];
            }
        }

        if ($statusCode === 401) {
            throw new UnauthorizedException((string) ($body['msg'] ?? $body['message'] ?? 'Unauthorized: invalid or missing Coinglass API key.'), $body);
        }

        if ($statusCode === 404) {
            throw new NotFoundException((string) ($body['msg'] ?? $body['message'] ?? 'The requested Coinglass resource was not found.'), $body);
        }

        if ($statusCode === 429) {
            throw new RateLimitException(
                (string) ($body['msg'] ?? $body['message'] ?? 'Coinglass API rate limit exceeded.'),
                $body,
                $this->parseRetryAfter($response->getHeaderLine('Retry-After')),
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw ApiException::fromResponse($statusCode, $body);
        }

        // 2xx HTTP response: unwrap the {"code","msg","data"} envelope.
        $code = isset($body['code']) ? (string) $body['code'] : '0';
        if ($code !== '0' && $code !== '') {
            throw ApiException::fromResponse($statusCode, $body);
        }

        return $body['data'] ?? null;
    }

    private function parseRetryAfter(string $header): ?int
    {
        if ($header === '' || !is_numeric($header)) {
            return null;
        }

        return (int) $header;
    }
}
