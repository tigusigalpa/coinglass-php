<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;

/**
 * Entry point for the Coinglass real-time WebSocket API
 * (https://docs.coinglass.com/reference/ws-getting-started).
 *
 * Implements just enough of RFC 6455 — the client handshake, masked text
 * frames, and orderly close — directly on top of PHP streams
 * (`stream_socket_client()`), so no third-party WebSocket library is
 * required.
 *
 * @example
 * ```php
 * $stream = $client->websocket()->connect();
 * $stream->subscribe(Channels::liquidationOrders());
 * $stream->listen(function (Message $message) {
 *     foreach ($message->collection() as $record) {
 *         echo $record->symbol . "\n";
 *     }
 * });
 * ```
 */
final class CoinGlassWebSocketClient
{
    /**
     * RFC 6455 magic GUID used to compute Sec-WebSocket-Accept from the
     * client's Sec-WebSocket-Key.
     */
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /** Maximum total size of the HTTP upgrade response headers. */
    private const MAX_HANDSHAKE_HEADER_SIZE = 16_384;

    public function __construct(private readonly CoinGlassWebSocketConfig $config)
    {
    }

    /**
     * Convenience factory for standalone (non-Laravel) usage.
     *
     * @param array<string, mixed> $options Optional overrides: base_url, connect_timeout, ping_interval.
     */
    public static function make(string $apiKey, array $options = []): self
    {
        return new self(CoinGlassWebSocketConfig::fromArray(['api_key' => $apiKey] + $options));
    }

    /**
     * Open a single WebSocket connection to the Coinglass server and
     * perform the RFC 6455 handshake, authenticated via the cg-api-key
     * query parameter.
     *
     * @throws WebSocketException
     */
    public function connect(): CoinGlassStream
    {
        $parsed = parse_url($this->config->baseUrl);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new WebSocketException("Invalid Coinglass WebSocket base URL: {$this->config->baseUrl}");
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['ws', 'wss'], true)) {
            throw new WebSocketException("Unsupported WebSocket scheme: {$scheme}");
        }

        $transport = $scheme === 'wss' ? 'ssl' : 'tcp';
        $port = $parsed['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $remote = "{$transport}://{$parsed['host']}:{$port}";

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->config->connectTimeout, STREAM_CLIENT_CONNECT);

        if ($socket === false) {
            throw new WebSocketException("Failed to connect to the Coinglass WebSocket API: {$errstr} ({$errno})");
        }

        self::setTimeout($socket, $this->config->connectTimeout);

        $queryParams = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
        }
        $queryParams['cg-api-key'] = $this->config->apiKey;

        $path = ($parsed['path'] ?? '/') . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $key = base64_encode(random_bytes(16));

        $host = $parsed['host'];
        $defaultPort = $scheme === 'wss' ? 443 : 80;
        if (isset($parsed['port']) && $parsed['port'] !== $defaultPort) {
            $host .= ':' . $parsed['port'];
        }

        $request = "GET {$path} HTTP/1.1\r\n"
            . "Host: {$host}\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . "\r\n";

        try {
            self::writeAll($socket, $request);
        } catch (WebSocketException) {
            fclose($socket);
            throw new WebSocketException('Failed to send the WebSocket handshake request.');
        }

        $statusLine = fgets($socket);
        if ($statusLine === false || preg_match('/^HTTP\/\d\.\d 101(?:\s|$)/', $statusLine) !== 1) {
            fclose($socket);
            throw new WebSocketException('Coinglass WebSocket handshake failed: ' . trim((string) $statusLine));
        }

        $headers = [];
        $headerSize = strlen($statusLine);
        while (($line = fgets($socket)) !== false) {
            $headerSize += strlen($line);
            if ($headerSize > self::MAX_HANDSHAKE_HEADER_SIZE) {
                fclose($socket);
                throw new WebSocketException('Coinglass WebSocket handshake failed: response headers are too large.');
            }

            $line = trim($line);
            if ($line === '') {
                break;
            }
            [$name, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
            $name = strtolower($name);
            $headers[$name] = isset($headers[$name]) ? $headers[$name] . ',' . $value : $value;
        }

        if (!isset($headers['upgrade']) || strcasecmp($headers['upgrade'], 'websocket') !== 0 || !self::headerHasToken($headers['connection'] ?? '', 'upgrade')) {
            fclose($socket);
            throw new WebSocketException('Coinglass WebSocket handshake failed: invalid upgrade response headers.');
        }

        $expectedAccept = base64_encode(sha1($key . self::GUID, true));
        if (($headers['sec-websocket-accept'] ?? '') !== $expectedAccept) {
            fclose($socket);
            throw new WebSocketException('Coinglass WebSocket handshake failed: invalid Sec-WebSocket-Accept header.');
        }

        self::setTimeout($socket, 0.0);
        stream_set_blocking($socket, true);

        return new CoinGlassStream($socket, $this->config);
    }

    private static function headerHasToken(string $value, string $token): bool
    {
        foreach (explode(',', $value) as $part) {
            if (strcasecmp(trim($part), $token) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param resource $socket */
    private static function writeAll($socket, string $data): void
    {
        $written = 0;
        $length = strlen($data);

        while ($written < $length) {
            $bytes = fwrite($socket, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new WebSocketException('Failed to write to the Coinglass WebSocket connection.');
            }
            $written += $bytes;
        }
    }

    /** @param resource $socket */
    private static function setTimeout($socket, float $timeout): void
    {
        $timeout = max(0.0, $timeout);
        $seconds = (int) floor($timeout);
        $microseconds = (int) round(($timeout - $seconds) * 1_000_000);

        if ($microseconds === 1_000_000) {
            $seconds++;
            $microseconds = 0;
        }

        stream_set_timeout($socket, $seconds, $microseconds);
    }
}
