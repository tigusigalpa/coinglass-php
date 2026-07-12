<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

/**
 * Builders for the Coinglass WebSocket channel names documented at
 * https://docs.coinglass.com/reference/ws-getting-started.
 */
final class Channels
{
    /**
     * Real-time liquidation orders stream.
     *
     * @see https://docs.coinglass.com/reference/ws-liquidation-order
     */
    public static function liquidationOrders(): string
    {
        return 'liquidation_orders';
    }

    /**
     * Real-time spot trades stream for the given exchange/symbol pair,
     * filtered to trades worth at least $minVolumeUsd.
     *
     * @see https://docs.coinglass.com/reference/websocket_spot_trades
     */
    public static function spotTrades(string $exchange, string $symbol, int $minVolumeUsd): string
    {
        return sprintf('spot_trades@%s_%s@%d', $exchange, $symbol, $minVolumeUsd);
    }

    /**
     * Real-time futures trades stream for the given exchange/symbol pair,
     * filtered to trades worth at least $minVolumeUsd.
     *
     * @see https://docs.coinglass.com/reference/websocket_futures_trades
     */
    public static function futuresTrades(string $exchange, string $symbol, int $minVolumeUsd): string
    {
        return sprintf('futures_trades@%s_%s@%d', $exchange, $symbol, $minVolumeUsd);
    }

    /**
     * Real-time futures ticker snapshot stream for the given exchange/symbol pair.
     *
     * @see https://docs.coinglass.com/reference/websocket_futures_ticker
     */
    public static function futuresTicker(string $exchange, string $symbol): string
    {
        return sprintf('futures_ticker@%s_%s', $exchange, $symbol);
    }
}
