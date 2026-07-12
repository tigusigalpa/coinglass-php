<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Laravel;

use Illuminate\Support\ServiceProvider;
use Tigusigalpa\CoinGlass\CoinGlassClient;
use Tigusigalpa\CoinGlass\CoinGlassConfig;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketClient;

/**
 * Laravel service provider for the Coinglass SDK.
 *
 * Registers the package configuration and binds a shared {@see CoinGlassClient}
 * instance into the container, built from the `config/coinglass.php` values.
 */
final class CoinGlassServiceProvider extends ServiceProvider
{
    /**
     * Path to the package's default configuration file.
     */
    private function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/coinglass.php';
    }

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'coinglass');

        $this->app->singleton(CoinGlassClient::class, static function ($app): CoinGlassClient {
            $config = CoinGlassConfig::fromArray((array) $app['config']->get('coinglass', []));

            return new CoinGlassClient($config);
        });

        $this->app->alias(CoinGlassClient::class, 'coinglass');

        $this->app->bind(CoinGlassWebSocketClient::class, static function ($app): CoinGlassWebSocketClient {
            $apiKey = (string) $app['config']->get('coinglass.api_key', '');
            $wsOptions = (array) $app['config']->get('coinglass.websocket', []);

            return CoinGlassWebSocketClient::make($apiKey, $wsOptions);
        });
    }

    /**
     * Bootstrap package services, publishing the config file for Laravel applications.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('coinglass.php'),
            ], 'coinglass-config');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [CoinGlassClient::class, 'coinglass', CoinGlassWebSocketClient::class];
    }
}
