<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\WebSocket\Channels;

final class ChannelsTest extends TestCase
{
    public function test_liquidation_orders_channel(): void
    {
        self::assertSame('liquidation_orders', Channels::liquidationOrders());
    }

    public function test_spot_trades_channel(): void
    {
        self::assertSame('spot_trades@Binance_BTCUSDT@10000', Channels::spotTrades('Binance', 'BTCUSDT', 10000));
    }

    public function test_futures_trades_channel(): void
    {
        self::assertSame('futures_trades@Binance_BTCUSDT@10000', Channels::futuresTrades('Binance', 'BTCUSDT', 10000));
    }

    public function test_futures_ticker_channel(): void
    {
        self::assertSame('futures_ticker@Binance_BTCUSDT', Channels::futuresTicker('Binance', 'BTCUSDT'));
    }
}
