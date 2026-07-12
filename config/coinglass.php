<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Coinglass API Key
    |--------------------------------------------------------------------------
    |
    | Your Coinglass API v4 key, sent on every request via the CG-API-KEY
    | header. Get one at https://coinglass.com.
    |
    */
    'api_key' => env('COINGLASS_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('COINGLASS_BASE_URL', 'https://open-api-v4.coinglass.com'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds.
    |
    */
    'timeout' => env('COINGLASS_TIMEOUT', 15.0),

    /*
    |--------------------------------------------------------------------------
    | Retry Attempts & Delay
    |--------------------------------------------------------------------------
    |
    | Number of automatic retries on HTTP 429 (rate limited) responses, and
    | the base delay (in seconds) used for exponential backoff between
    | retries: retry_delay * 2^(attempt - 1).
    |
    */
    'retry_attempts' => env('COINGLASS_RETRY_ATTEMPTS', 3),

    'retry_delay' => env('COINGLASS_RETRY_DELAY', 1.0),

    /*
    |--------------------------------------------------------------------------
    | WebSocket API
    |--------------------------------------------------------------------------
    |
    | Configuration for the real-time WebSocket API. base_url points to the
    | dedicated Coinglass WebSocket host (independent of the REST base_url
    | above); connect_timeout and ping_interval are both in seconds.
    |
    */
    'websocket' => [
        'base_url' => env('COINGLASS_WS_BASE_URL', 'wss://open-ws.coinglass.com/ws-api'),
        'connect_timeout' => env('COINGLASS_WS_CONNECT_TIMEOUT', 10.0),
        'ping_interval' => env('COINGLASS_WS_PING_INTERVAL', 20.0),
    ],
];
