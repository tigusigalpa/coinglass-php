<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketConfig;

final class CoinGlassWebSocketConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $config = new CoinGlassWebSocketConfig(apiKey: 'test-key');

        self::assertSame('test-key', $config->apiKey);
        self::assertSame('wss://open-ws.coinglass.com/ws-api', $config->baseUrl);
        self::assertSame(10.0, $config->connectTimeout);
        self::assertSame(20.0, $config->pingInterval);
    }

    public function test_from_array_overrides(): void
    {
        $config = CoinGlassWebSocketConfig::fromArray([
            'api_key' => 'test-key',
            'base_url' => 'wss://example.test/ws-api/',
            'connect_timeout' => 5,
            'ping_interval' => 15,
        ]);

        self::assertSame('test-key', $config->apiKey);
        self::assertSame('wss://example.test/ws-api', $config->baseUrl);
        self::assertSame(5.0, $config->connectTimeout);
        self::assertSame(15.0, $config->pingInterval);
    }

    public function test_from_array_uses_defaults_for_missing_keys(): void
    {
        $config = CoinGlassWebSocketConfig::fromArray(['api_key' => 'test-key']);

        self::assertSame('wss://open-ws.coinglass.com/ws-api', $config->baseUrl);
        self::assertSame(10.0, $config->connectTimeout);
        self::assertSame(20.0, $config->pingInterval);
    }
}
