<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Tigusigalpa\CoinGlass\Laravel\CoinGlassServiceProvider;

/**
 * Base test case for Laravel-integrated (Feature) tests, powered by Orchestra Testbench.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [CoinGlassServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('coinglass.api_key', 'test-key');
    }
}
