<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Exceptions;

use Throwable;

/**
 * Thrown when the Coinglass API responds with HTTP 429 (Too Many Requests)
 * and the configured number of automatic retries has been exhausted.
 */
class RateLimitException extends ApiException
{
    /**
     * @param string               $message      Human-readable error message.
     * @param array<string, mixed> $responseBody Decoded JSON response body, if available.
     * @param int|null             $retryAfter   Value of the `Retry-After` header (seconds), if present.
     * @param Throwable|null       $previous     Previous exception used for chaining.
     * @param string|null          $rawBody      Original response body, if available.
     */
    public function __construct(
        string $message = 'Coinglass API rate limit exceeded.',
        array $responseBody = [],
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
        ?string $rawBody = null,
    ) {
        parent::__construct($message, 429, $responseBody, null, $previous, $rawBody);
    }
}
