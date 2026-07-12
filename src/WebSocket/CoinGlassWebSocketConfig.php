<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

/**
 * Immutable configuration object for the Coinglass WebSocket client.
 */
final class CoinGlassWebSocketConfig
{
    /**
     * @param string $apiKey         Coinglass API v4 key, sent as the cg-api-key query parameter.
     * @param string $baseUrl        WebSocket endpoint (no trailing slash).
     * @param float  $connectTimeout Timeout (in seconds) for the TCP/TLS connection and handshake.
     * @param float  $pingInterval   How often (in seconds) to send the "ping" heartbeat Coinglass expects.
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = 'wss://open-ws.coinglass.com/ws-api',
        public readonly float $connectTimeout = 10.0,
        public readonly float $pingInterval = 20.0,
    ) {
    }

    /**
     * Create a configuration instance from a plain associative array.
     *
     * Recognized keys: `api_key`, `base_url`, `connect_timeout`, `ping_interval`.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            baseUrl: rtrim((string) ($config['base_url'] ?? 'wss://open-ws.coinglass.com/ws-api'), '/'),
            connectTimeout: (float) ($config['connect_timeout'] ?? 10.0),
            pingInterval: (float) ($config['ping_interval'] ?? 20.0),
        );
    }

    /**
     * Create a configuration instance from environment variables.
     *
     * Recognized variables: `COINGLASS_API_KEY`, `COINGLASS_WS_BASE_URL`,
     * `COINGLASS_WS_CONNECT_TIMEOUT`, `COINGLASS_WS_PING_INTERVAL`.
     */
    public static function fromEnv(): self
    {
        return self::fromArray([
            'api_key' => getenv('COINGLASS_API_KEY') ?: '',
            'base_url' => getenv('COINGLASS_WS_BASE_URL') ?: 'wss://open-ws.coinglass.com/ws-api',
            'connect_timeout' => getenv('COINGLASS_WS_CONNECT_TIMEOUT') ?: 10.0,
            'ping_interval' => getenv('COINGLASS_WS_PING_INTERVAL') ?: 20.0,
        ]);
    }
}
