<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass;

/**
 * Immutable configuration object for the Coinglass SDK.
 *
 * Holds the API key, base URL, and HTTP/retry tuning parameters used by
 * {@see CoinGlassClient} to build and send requests.
 */
final class CoinGlassConfig
{
    /**
     * @param string $apiKey        Coinglass API v4 key, sent via the CG-API-KEY header.
     * @param string $baseUrl       Base URL of the Coinglass API (no trailing slash).
     * @param float  $timeout       Request timeout in seconds.
     * @param int    $retryAttempts Number of automatic retries on HTTP 429 responses.
     * @param float  $retryDelay    Base delay (in seconds) for exponential backoff between retries.
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = 'https://open-api-v4.coinglass.com',
        public readonly float $timeout = 15.0,
        public readonly int $retryAttempts = 3,
        public readonly float $retryDelay = 1.0,
    ) {
    }

    /**
     * Create a configuration instance from a plain associative array.
     *
     * Recognized keys: `api_key`, `base_url`, `timeout`, `retry_attempts`, `retry_delay`.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://open-api-v4.coinglass.com'), '/'),
            timeout: (float) ($config['timeout'] ?? 15.0),
            retryAttempts: (int) ($config['retry_attempts'] ?? 3),
            retryDelay: (float) ($config['retry_delay'] ?? 1.0),
        );
    }

    /**
     * Create a configuration instance from environment variables.
     *
     * Recognized variables: `COINGLASS_API_KEY`, `COINGLASS_BASE_URL`,
     * `COINGLASS_TIMEOUT`, `COINGLASS_RETRY_ATTEMPTS`, `COINGLASS_RETRY_DELAY`.
     */
    public static function fromEnv(): self
    {
        return self::fromArray([
            'api_key' => getenv('COINGLASS_API_KEY') ?: '',
            'base_url' => getenv('COINGLASS_BASE_URL') ?: 'https://open-api-v4.coinglass.com',
            'timeout' => getenv('COINGLASS_TIMEOUT') ?: 15.0,
            'retry_attempts' => getenv('COINGLASS_RETRY_ATTEMPTS') ?: 3,
            'retry_delay' => getenv('COINGLASS_RETRY_DELAY') ?: 1.0,
        ]);
    }
}
