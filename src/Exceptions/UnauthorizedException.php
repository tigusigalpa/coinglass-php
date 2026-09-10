<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Exceptions;

use Throwable;

/**
 * Thrown when the Coinglass API responds with HTTP 401 (Unauthorized),
 * typically indicating a missing, invalid, or revoked API key.
 */
class UnauthorizedException extends ApiException
{
    /**
     * @param string               $message      Human-readable error message.
     * @param array<string, mixed> $responseBody Decoded JSON response body, if available.
     * @param Throwable|null       $previous     Previous exception used for chaining.
     * @param string|null          $rawBody      Original response body, if available.
     */
    public function __construct(
        string $message = 'Unauthorized: invalid or missing Coinglass API key.',
        array $responseBody = [],
        ?Throwable $previous = null,
        ?string $rawBody = null,
    ) {
        parent::__construct($message, 401, $responseBody, null, $previous, $rawBody);
    }
}
