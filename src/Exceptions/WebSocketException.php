<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Exceptions;

/**
 * Thrown for any failure related to the Coinglass WebSocket API: connection
 * failures, handshake errors, and read/write errors on an open stream.
 */
class WebSocketException extends CoinGlassException
{
}
