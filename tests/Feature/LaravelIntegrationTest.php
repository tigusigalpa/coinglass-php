<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Feature;

use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\Laravel\Facades\CoinGlass;
use Tigusigalpa\CoinGlass\Resources\EtfResource;
use Tigusigalpa\CoinGlass\Resources\FuturesResource;
use Tigusigalpa\CoinGlass\Tests\TestCase;

final class LaravelIntegrationTest extends TestCase
{
    public function testConfigFileIsMerged(): void
    {
        self::assertSame('https://open-api-v4.coinglass.com', config('coinglass.base_url'));
        self::assertSame(3, config('coinglass.retry_attempts'));
        self::assertSame(1.0, config('coinglass.retry_delay'));
        self::assertSame('test-key', config('coinglass.api_key'));
    }

    public function testServiceProviderBindsSharedClientInstance(): void
    {
        $client = $this->app->make(CoinGlassClient::class);

        self::assertInstanceOf(CoinGlassClient::class, $client);
        self::assertSame($client, $this->app->make(CoinGlassClient::class));
        self::assertSame($client, $this->app->make('coinglass'));
    }

    public function testFacadeResolvesUnderlyingClientResources(): void
    {
        self::assertInstanceOf(FuturesResource::class, CoinGlass::futures());
        self::assertInstanceOf(EtfResource::class, CoinGlass::etf());
    }
}
