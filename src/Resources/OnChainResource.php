<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass On-Chain endpoint group.
 */
final class OnChainResource extends AbstractResource
{
    /** Exchange assets. `GET /api/exchange/assets`. */
    public function exchangeAssets(): mixed
    {
        return $this->request('/api/exchange/assets');
    }

    /** Exchange balance list. `GET /api/exchange/balance/list`. */
    public function exchangeBalanceList(?string $symbol = null, ?string $exchange = null): mixed
    {
        return $this->request('/api/exchange/balance/list', [
            'symbol' => $symbol,
            'exchange' => $exchange,
        ]);
    }

    /** On-chain ERC-20 transfers. `GET /api/exchange/chain/tx/list`. */
    public function exchangeOnChainTransfers(?string $symbol = null, ?int $limit = null): mixed
    {
        return $this->request('/api/exchange/chain/tx/list', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }
}
