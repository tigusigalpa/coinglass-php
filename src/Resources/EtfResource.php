<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass ETF endpoint group.
 *
 * @example
 * ```php
 * $flows = $client->etf()->bitcoinFlowHistory('1w', 24);
 * ```
 */
final class EtfResource extends AbstractResource
{
    /** Bitcoin ETF list. `GET /api/etf/bitcoin/list`. */
    public function bitcoinList(): mixed
    {
        return $this->request('/api/etf/bitcoin/list');
    }

    /** Bitcoin ETF flow history. `GET /api/etf/bitcoin/flow-history`. */
    public function bitcoinFlowHistory(string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/etf/bitcoin/flow-history', [
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Bitcoin ETF net assets history. `GET /api/etf/bitcoin/net-assets/history`. */
    public function bitcoinNetAssetsHistory(string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/etf/bitcoin/net-assets/history', [
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Ethereum ETF list. `GET /api/etf/ethereum/list`. */
    public function ethereumList(): mixed
    {
        return $this->request('/api/etf/ethereum/list');
    }

    /** Ethereum ETF flow history. `GET /api/etf/ethereum/flow-history`. */
    public function ethereumFlowHistory(string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/etf/ethereum/flow-history', [
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Grayscale holdings list. `GET /api/grayscale/holdings-list`. */
    public function grayscaleHoldings(): mixed
    {
        return $this->request('/api/grayscale/holdings-list');
    }
}
