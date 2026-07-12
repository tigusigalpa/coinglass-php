<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\Resources\EtfResource;
use Tigusigalpa\CoinGlass\Resources\FuturesResource;
use Tigusigalpa\CoinGlass\Resources\IndicatorsResource;
use Tigusigalpa\CoinGlass\Resources\OnChainResource;
use Tigusigalpa\CoinGlass\Resources\OptionsResource;
use Tigusigalpa\CoinGlass\Resources\SpotResource;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketClient;

/**
 * Laravel facade exposing the fluent Coinglass API.
 *
 * @method static FuturesResource futures()
 * @method static SpotResource spot()
 * @method static OptionsResource options()
 * @method static EtfResource etf()
 * @method static OnChainResource onChain()
 * @method static IndicatorsResource indicators()
 * @method static CoinGlassWebSocketClient websocket(array $options = [])
 * @method static mixed request(string $path, array $query = [])
 *
 * @see CoinGlassClient
 */
final class CoinGlass extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     */
    protected static function getFacadeAccessor(): string
    {
        return CoinGlassClient::class;
    }
}
