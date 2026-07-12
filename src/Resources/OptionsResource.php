<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Resources;

/**
 * Fluent access to the Coinglass Options endpoint group.
 */
final class OptionsResource extends AbstractResource
{
    /** Option max pain. `GET /api/option/max-pain`. */
    public function maxPain(string $underlying, ?int $expiry = null): mixed
    {
        return $this->request('/api/option/max-pain', [
            'underlying' => $underlying,
            'expiry' => $expiry,
        ]);
    }

    /** Options info. `GET /api/option/info`. */
    public function info(string $underlying, ?int $expiry = null): mixed
    {
        return $this->request('/api/option/info', [
            'underlying' => $underlying,
            'expiry' => $expiry,
        ]);
    }

    /** Exchange open-interest history. `GET /api/option/exchange-oi-history`. */
    public function exchangeOiHistory(string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/option/exchange-oi-history', [
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }

    /** Exchange volume history. `GET /api/option/exchange-vol-history`. */
    public function exchangeVolHistory(string $interval, ?int $limit = null): mixed
    {
        return $this->request('/api/option/exchange-vol-history', [
            'interval' => $interval,
            'limit' => $limit,
        ]);
    }
}
