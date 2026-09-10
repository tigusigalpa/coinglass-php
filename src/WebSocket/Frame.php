<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;

/**
 * Low-level RFC 6455 WebSocket framing over a plain PHP stream resource
 * (as returned by `stream_socket_client()`).
 *
 * This is intentionally minimal: it only implements what the Coinglass
 * WebSocket API needs — masked text frames from the client, unmasked text
 * frames from the server, fragmented-message reassembly, and close/ping/pong
 * control frames — so the SDK does not need to pull in a WebSocket library.
 *
 * @internal
 */
final class Frame
{
    /** Maximum payload size for one frame or reassembled message (16 MiB). */
    private const MAX_MESSAGE_PAYLOAD = 16 << 20;

    public const OP_CONTINUATION = 0x0;
    public const OP_TEXT = 0x1;
    public const OP_BINARY = 0x2;
    public const OP_CLOSE = 0x8;
    public const OP_PING = 0x9;
    public const OP_PONG = 0xA;

    /**
     * Write a single, unfragmented, masked frame. Per RFC 6455, every frame
     * sent by a client MUST be masked with a random 32-bit key.
     *
     * @param resource $socket
     */
    public static function write($socket, int $opcode, string $payload): void
    {
        $length = strlen($payload);
        if ($length > self::MAX_MESSAGE_PAYLOAD) {
            throw new WebSocketException('WebSocket message payload is too large.');
        }
        $header = chr(0x80 | $opcode); // FIN=1

        if ($length <= 125) {
            $header .= chr($length | 0x80);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(126 | 0x80) . pack('n', $length);
        } else {
            $header .= chr(127 | 0x80) . pack('J', $length);
        }

        $maskKey = random_bytes(4);
        $header .= $maskKey;

        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $maskKey[$i % 4];
        }

        self::writeAll($socket, $header . $masked);
    }

    /**
     * Read a single frame, reassembling continuation frames until FIN=1.
     * Server frames sent by Coinglass are unmasked, but masked frames are
     * unmasked transparently too in case an intermediary sends one.
     *
     * @param resource $socket
     *
     * @return array{opcode: int, payload: string}
     */
    public static function readMessage($socket): array
    {
        $first = self::readFrame($socket);

        if (in_array($first['opcode'], [self::OP_CLOSE, self::OP_PING, self::OP_PONG], true)) {
            return ['opcode' => $first['opcode'], 'payload' => $first['payload']];
        }

        $opcode = $first['opcode'];
        if ($opcode === self::OP_CONTINUATION) {
            throw new WebSocketException('Received a WebSocket continuation frame without an initial message.');
        }
        $payload = $first['payload'];
        $fin = $first['fin'];

        while (!$fin) {
            $next = self::readFrame($socket);
            if ($next['opcode'] !== self::OP_CONTINUATION) {
                throw new WebSocketException('Expected a continuation frame while reassembling a fragmented message.');
            }
            if (strlen($payload) + strlen($next['payload']) > self::MAX_MESSAGE_PAYLOAD) {
                throw new WebSocketException('WebSocket message payload is too large.');
            }
            $payload .= $next['payload'];
            $fin = $next['fin'];
        }

        return ['opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * Read and decode a single raw frame header + payload.
     *
     * @param resource $socket
     *
     * @return array{fin: bool, opcode: int, payload: string}
     */
    public static function readFrame($socket): array
    {
        $head = self::readExact($socket, 2);
        $byte1 = ord($head[0]);
        $byte2 = ord($head[1]);

        $fin = ($byte1 & 0x80) !== 0;
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $length = $byte2 & 0x7F;

        if ($length === 126) {
            $ext = self::readExact($socket, 2);
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', $ext);
            $length = $unpacked[1];
        } elseif ($length === 127) {
            $ext = self::readExact($socket, 8);
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('J', $ext);
            $length = $unpacked[1];
        }

        if (!in_array($opcode, [self::OP_CONTINUATION, self::OP_TEXT, self::OP_BINARY, self::OP_CLOSE, self::OP_PING, self::OP_PONG], true)) {
            throw new WebSocketException('Received a WebSocket frame with an unsupported opcode.');
        }

        if ($length < 0 || $length > self::MAX_MESSAGE_PAYLOAD) {
            throw new WebSocketException('WebSocket frame payload is too large.');
        }

        if ($opcode >= self::OP_CLOSE && (!$fin || $length > 125)) {
            throw new WebSocketException('Received an invalid fragmented or oversized WebSocket control frame.');
        }

        $maskKey = $masked ? self::readExact($socket, 4) : null;
        $payload = $length > 0 ? self::readExact($socket, $length) : '';

        if ($masked && $maskKey !== null) {
            $unmasked = '';
            for ($i = 0; $i < $length; $i++) {
                $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $unmasked;
        }

        return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * @param resource $socket
     */
    private static function readExact($socket, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                if (!empty($meta['timed_out'])) {
                    throw new WebSocketException('Timed out reading from the Coinglass WebSocket connection.');
                }
                throw new WebSocketException('The Coinglass WebSocket connection was closed unexpectedly.');
            }
            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param resource $socket
     */
    private static function writeAll($socket, string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $n = fwrite($socket, substr($data, $written));
            if ($n === false || $n === 0) {
                throw new WebSocketException('Failed to write to the Coinglass WebSocket connection.');
            }
            $written += $n;
        }
    }
}
