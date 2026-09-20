<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassStream;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketConfig;
use Tigusigalpa\CoinGlass\WebSocket\Frame;

final class CoinGlassStreamTest extends TestCase
{
    /**
     * @return array{0: resource, 1: resource}
     */
    private function socketPair(): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($server, $error);

        $address = stream_socket_get_name($server, false);
        self::assertNotFalse($address);
        $client = stream_socket_client("tcp://{$address}", $errno, $error, 1.0);
        self::assertNotFalse($client, $error);
        $peer = stream_socket_accept($server, 1.0);
        fclose($server);
        self::assertNotFalse($peer);

        stream_set_timeout($client, 1);
        stream_set_timeout($peer, 1);

        return [$client, $peer];
    }

    /** @param resource $socket */
    private function writeServerFrame($socket, int $opcode, string $payload): void
    {
        $length = strlen($payload);
        self::assertLessThanOrEqual(125, $length, 'Test helper only encodes short server frames.');
        $frame = chr(0x80 | $opcode) . chr($length) . $payload;
        $written = 0;

        while ($written < strlen($frame)) {
            $bytes = fwrite($socket, substr($frame, $written));
            self::assertNotFalse($bytes);
            self::assertGreaterThan(0, $bytes);
            $written += $bytes;
        }
    }

    /** @param resource $socket */
    private function closeSocket($socket): void
    {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    public function testSubscribesAndUnsubscribesOverTheWire(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 60));

        $stream->subscribe();
        self::assertSame([], $stream->subscriptions());

        $stream->subscribe('liquidation_orders', 'futures_ticker@Binance_BTCUSDT');
        self::assertSame(['liquidation_orders', 'futures_ticker@Binance_BTCUSDT'], $stream->subscriptions());
        $subscribe = Frame::readFrame($peer);
        self::assertSame(Frame::OP_TEXT, $subscribe['opcode']);
        self::assertSame([
            'method' => 'subscribe',
            'channels' => ['liquidation_orders', 'futures_ticker@Binance_BTCUSDT'],
        ], json_decode($subscribe['payload'], true, 512, JSON_THROW_ON_ERROR));

        $stream->unsubscribe();
        $stream->unsubscribe('liquidation_orders');
        self::assertSame(['futures_ticker@Binance_BTCUSDT'], $stream->subscriptions());
        $unsubscribe = Frame::readFrame($peer);
        self::assertSame([
            'method' => 'unsubscribe',
            'channels' => ['liquidation_orders'],
        ], json_decode($unsubscribe['payload'], true, 512, JSON_THROW_ON_ERROR));

        $stream->close();
        self::assertTrue($stream->isClosed());
        self::assertSame(Frame::OP_CLOSE, Frame::readFrame($peer)['opcode']);
        $this->closeSocket($peer);
    }

    public function testReadHandlesControlFramesAndReturnsTheNextJsonMessage(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 60));

        $this->writeServerFrame($peer, Frame::OP_PING, 'check');
        $this->writeServerFrame($peer, Frame::OP_PONG, '');
        $this->writeServerFrame($peer, Frame::OP_TEXT, 'pong');
        $this->writeServerFrame($peer, Frame::OP_TEXT, '{"channel":"liquidation_orders","data":[{"symbol":"BTC"}]}');

        $message = $stream->read(0.5);

        self::assertNotNull($message);
        self::assertSame('liquidation_orders', $message->channel);
        self::assertSame('BTC', $message->data[0]['symbol']);

        $pong = Frame::readFrame($peer);
        self::assertSame(Frame::OP_PONG, $pong['opcode']);
        self::assertSame('check', $pong['payload']);

        $stream->close();
        $this->closeSocket($peer);
    }

    public function testReadTimesOutSendsHeartbeatAndClosesOnRemoteClose(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 0.0));

        self::assertNull($stream->read(0.0));
        $ping = Frame::readFrame($peer);
        self::assertSame(Frame::OP_TEXT, $ping['opcode']);
        self::assertSame('ping', $ping['payload']);

        $this->writeServerFrame($peer, Frame::OP_CLOSE, '');
        self::assertNull($stream->read(0.5));
        self::assertTrue($stream->isClosed());
        $this->closeSocket($peer);
    }

    public function testClosedStreamsAndInvalidTimeoutsAreRejected(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 60));

        try {
            $stream->read(-0.1);
            self::fail('Expected a negative timeout to be rejected.');
        } catch (WebSocketException $exception) {
            self::assertStringContainsString('cannot be negative', $exception->getMessage());
        }

        $stream->close();
        $stream->close();
        self::assertTrue($stream->isClosed());

        try {
            $stream->subscribe('liquidation_orders');
            self::fail('Expected sending on a closed stream to fail.');
        } catch (WebSocketException $exception) {
            self::assertStringContainsString('Cannot send', $exception->getMessage());
        }

        try {
            $stream->read();
            self::fail('Expected reading from a closed stream to fail.');
        } catch (WebSocketException $exception) {
            self::assertStringContainsString('Cannot read', $exception->getMessage());
        }

        $this->closeSocket($peer);
    }

    public function testListenDeliversMessagesUntilTheRemotePeerCloses(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 60));
        $messages = [];

        $this->writeServerFrame($peer, Frame::OP_TEXT, '{"channel":"spot_trades","data":{"symbol":"BTCUSDT"}}');
        $this->writeServerFrame($peer, Frame::OP_CLOSE, '');

        $stream->listen(static function ($message) use (&$messages): void {
            $messages[] = $message->channel;
        });

        self::assertSame(['spot_trades'], $messages);
        self::assertTrue($stream->isClosed());
        $this->closeSocket($peer);
    }

    public function testListenForwardsReadErrorsToItsErrorCallback(): void
    {
        [$socket, $peer] = $this->socketPair();
        $stream = new CoinGlassStream($socket, new CoinGlassWebSocketConfig(apiKey: 'test-key', pingInterval: 60));
        fclose($peer);
        $errors = [];

        $stream->listen(
            static function (): void {
                self::fail('No message should be delivered after the peer disconnects.');
            },
            static function (WebSocketException $exception) use (&$errors): void {
                $errors[] = $exception->getMessage();
            },
        );

        self::assertCount(1, $errors);
        self::assertTrue($stream->isClosed());
    }
}
