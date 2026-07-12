<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Exceptions;

use Throwable;

/**
 * Thrown when the Coinglass API returns a non-2xx HTTP response, or a 2xx
 * response whose envelope `code` field is not `"0"`, and the error does not
 * map to a more specific exception.
 */
class ApiException extends CoinGlassException
{
    /**
     * @param string               $message      Human-readable error message.
     * @param int                  $statusCode   HTTP status code returned by the API.
     * @param array<string, mixed> $responseBody Decoded JSON response body, if available.
     * @param string|null          $apiCode      The Coinglass envelope `code` field, if present.
     * @param Throwable|null       $previous     Previous exception used for chaining.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $responseBody = [],
        public readonly ?string $apiCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Build an instance from an HTTP status code and a decoded response body.
     *
     * @param array<string, mixed> $body
     */
    public static function fromResponse(int $statusCode, array $body, ?Throwable $previous = null): static
    {
        $message = (string) ($body['msg'] ?? $body['message'] ?? "Coinglass API request failed with status {$statusCode}");

        return new static($message, $statusCode, $body, isset($body['code']) ? (string) $body['code'] : null, $previous);
    }
}
