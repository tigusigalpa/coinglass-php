<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\WebSocket;

use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;

/**
 * A single, live connection to the Coinglass WebSocket API.
 *
 * Every subscription is multiplexed over one connection: call
 * {@see self::subscribe()} for as many channels as you like, then either
 * pull messages one at a time with {@see self::read()} or hand a callback to
 * {@see self::listen()} for a blocking read loop. The application-level
 * "ping" heartbeat Coinglass expects is sent automatically.
 */
final class CoinGlassStream
{
    /** @var resource */
    private $socket;

    private bool $closed = false;

    private float $lastPingAt;

    /** @var array<string, true> */
    private array $subscriptions = [];

    /**
     * @param resource $socket
     */
    public function __construct($socket, private readonly CoinGlassWebSocketConfig $config)
    {
        $this->socket = $socket;
        $this->lastPingAt = microtime(true);
    }

    /**
     * Subscribe to one or more channels, e.g. `Channels::liquidationOrders()`
     * or `Channels::futuresTicker('Binance', 'BTCUSDT')`.
     */
    public function subscribe(string ...$channels): void
    {
        if ($channels === []) {
            return;
        }

        $this->send(['method' => 'subscribe', 'channels' => $channels]);

        foreach ($channels as $channel) {
            $this->subscriptions[$channel] = true;
        }
    }

    /**
     * Unsubscribe from one or more channels.
     */
    public function unsubscribe(string ...$channels): void
    {
        if ($channels === []) {
            return;
        }

        $this->send(['method' => 'unsubscribe', 'channels' => $channels]);

        foreach ($channels as $channel) {
            unset($this->subscriptions[$channel]);
        }
    }

    /**
     * @return list<string> Channel names currently subscribed to.
     */
    public function subscriptions(): array
    {
        return array_keys($this->subscriptions);
    }

    /**
     * Wait for and return the next message, or null if no message arrives
     * within $timeout seconds. Defaults to the configured ping interval so
     * that repeated calls (e.g. in a custom loop) never miss a heartbeat.
     * Ping/pong and close frames are handled transparently and never
     * returned to the caller.
     */
    public function read(?float $timeout = null): ?Message
    {
        if ($this->closed) {
            throw new WebSocketException('Cannot read from a closed WebSocket stream.');
        }

        $waitSeconds = $timeout ?? $this->config->pingInterval;
        if ($waitSeconds < 0) {
            throw new WebSocketException('WebSocket read timeout cannot be negative.');
        }

        $deadline = microtime(true) + $waitSeconds;

        while (true) {
            try {
                $this->maybePing();
            } catch (WebSocketException $e) {
                $this->markClosed();
                throw $e;
            }

            $remaining = max(0.0, $deadline - microtime(true));
            $seconds = (int) floor($remaining);
            $microseconds = (int) round(($remaining - $seconds) * 1_000_000);
            if ($microseconds === 1_000_000) {
                $seconds++;
                $microseconds = 0;
            }

            $read = [$this->socket];
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                $this->markClosed();
                throw new WebSocketException('stream_select() failed while waiting for WebSocket data.');
            }
            if ($ready === 0) {
                return null;
            }

            // A zero stream timeout means "wait indefinitely" in PHP, so
            // keep a minimal deadline for a frame that arrives during a poll.
            self::setTimeout($this->socket, max(0.001, $remaining));
            try {
                $frame = Frame::readMessage($this->socket);
            } catch (WebSocketException $e) {
                $this->markClosed();
                throw $e;
            }

            switch ($frame['opcode']) {
                case Frame::OP_CLOSE:
                    $this->markClosed();

                    return null;

                case Frame::OP_PING:
                    try {
                        Frame::write($this->socket, Frame::OP_PONG, $frame['payload']);
                    } catch (WebSocketException $e) {
                        $this->markClosed();
                        throw $e;
                    }
                    continue 2;

                case Frame::OP_PONG:
                    continue 2;
            }

            if ($frame['payload'] === 'pong') {
                continue;
            }

            $message = Message::fromJson($frame['payload']);
            if ($message !== null) {
                return $message;
            }
        }
    }

    /**
     * Block, repeatedly calling $onMessage for every incoming message, until
     * the stream is closed. If $onError is given, exceptions raised while
     * reading are passed to it and the loop continues; otherwise they are
     * rethrown and the loop stops.
     *
     * @param callable(Message): void         $onMessage
     * @param (callable(WebSocketException): void)|null $onError
     */
    public function listen(callable $onMessage, ?callable $onError = null): void
    {
        while (!$this->closed) {
            try {
                $message = $this->read($this->config->pingInterval);
            } catch (WebSocketException $e) {
                if ($onError === null) {
                    throw $e;
                }
                $onError($e);
                continue;
            }

            if ($message !== null) {
                $onMessage($message);
            }
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Send a close frame and close the underlying socket. Safe to call more
     * than once.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        try {
            Frame::write($this->socket, Frame::OP_CLOSE, '');
        } catch (WebSocketException) {
            // Best-effort: the peer may have already gone away.
        }

        $this->markClosed();
    }

    private function markClosed(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        fclose($this->socket);
    }

    private function maybePing(): void
    {
        $now = microtime(true);
        if ($now - $this->lastPingAt < $this->config->pingInterval) {
            return;
        }

        Frame::write($this->socket, Frame::OP_TEXT, 'ping');
        $this->lastPingAt = $now;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): void
    {
        if ($this->closed) {
            throw new WebSocketException('Cannot send on a closed WebSocket stream.');
        }

        Frame::write($this->socket, Frame::OP_TEXT, json_encode($payload, JSON_THROW_ON_ERROR));
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
