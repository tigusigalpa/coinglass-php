<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Exceptions;

use Throwable;

/**
 * Thrown when the Coinglass API responds with HTTP 404 (Not Found),
 * indicating the requested resource or endpoint does not exist.
 */
class NotFoundException extends ApiException
{
    /**
     * @param string               $message      Human-readable error message.
     * @param array<string, mixed> $responseBody Decoded JSON response body, if available.
     * @param Throwable|null       $previous     Previous exception used for chaining.
     * @param string|null          $rawBody      Original response body, if available.
     */
    public function __construct(
        string $message = 'The requested Coinglass resource was not found.',
        array $responseBody = [],
        ?Throwable $previous = null,
        ?string $rawBody = null,
    ) {
        parent::__construct($message, 404, $responseBody, null, $previous, $rawBody);
    }
}
