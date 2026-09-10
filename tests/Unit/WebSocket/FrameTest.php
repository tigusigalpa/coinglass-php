<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;
use Tigusigalpa\CoinGlass\WebSocket\Frame;

final class FrameTest extends TestCase
{
    /** @return resource */
    private function memoryStream()
    {
        $stream = fopen('php://memory', 'r+b');
        self::assertNotFalse($stream);

        return $stream;
    }

    public function test_write_produces_a_masked_frame_that_unmasks_back_to_the_original_payload(): void
    {
        $stream = $this->memoryStream();
        $payload = 'hello coinglass';

        Frame::write($stream, Frame::OP_TEXT, $payload);
        rewind($stream);
        $data = stream_get_contents($stream);

        self::assertNotFalse($data);
        self::assertSame(0x80 | Frame::OP_TEXT, ord($data[0]));

        $lengthByte = ord($data[1]);
        self::assertNotSame(0, $lengthByte & 0x80, 'expected the masked bit to be set');

        $length = $lengthByte & 0x7F;
        self::assertSame(strlen($payload), $length);

        $maskKey = substr($data, 2, 4);
        $masked = substr($data, 6, $length);

        $unmasked = '';
        for ($i = 0; $i < $length; $i++) {
            $unmasked .= $masked[$i] ^ $maskKey[$i % 4];
        }

        self::assertSame($payload, $unmasked);

        fclose($stream);
    }

    public function test_read_frame_parses_an_unmasked_server_frame_with_extended_length(): void
    {
        $payload = str_repeat('a', 200); // forces the 126 extended-length form

        $stream = $this->memoryStream();
        fwrite($stream, chr(0x80 | Frame::OP_TEXT)); // FIN=1, opcode=text
        fwrite($stream, chr(126)); // unmasked, extended length follows
        fwrite($stream, pack('n', strlen($payload)));
        fwrite($stream, $payload);
        rewind($stream);

        $frame = Frame::readFrame($stream);

        self::assertTrue($frame['fin']);
        self::assertSame(Frame::OP_TEXT, $frame['opcode']);
        self::assertSame($payload, $frame['payload']);

        fclose($stream);
    }

    public function test_read_message_reassembles_a_fragmented_message(): void
    {
        $stream = $this->memoryStream();

        // First fragment: FIN=0, opcode=text, payload="hel"
        fwrite($stream, chr(0x00 | Frame::OP_TEXT));
        fwrite($stream, chr(3));
        fwrite($stream, 'hel');

        // Final fragment: FIN=1, opcode=continuation, payload="lo"
        fwrite($stream, chr(0x80 | Frame::OP_CONTINUATION));
        fwrite($stream, chr(2));
        fwrite($stream, 'lo');

        rewind($stream);

        $message = Frame::readMessage($stream);

        self::assertSame(Frame::OP_TEXT, $message['opcode']);
        self::assertSame('hello', $message['payload']);

        fclose($stream);
    }

    public function test_read_message_returns_control_frames_without_reassembly(): void
    {
        $stream = $this->memoryStream();
        fwrite($stream, chr(0x80 | Frame::OP_PING));
        fwrite($stream, chr(0));
        rewind($stream);

        $message = Frame::readMessage($stream);

        self::assertSame(Frame::OP_PING, $message['opcode']);
        self::assertSame('', $message['payload']);

        fclose($stream);
    }

    public function test_read_frame_rejects_a_payload_larger_than_the_safety_limit(): void
    {
        $stream = $this->memoryStream();
        fwrite($stream, chr(0x80 | Frame::OP_TEXT));
        fwrite($stream, chr(127));
        fwrite($stream, pack('J', (16 << 20) + 1));
        rewind($stream);

        $this->expectException(WebSocketException::class);
        Frame::readFrame($stream);
    }

    public function test_read_message_rejects_an_orphaned_continuation_frame(): void
    {
        $stream = $this->memoryStream();
        fwrite($stream, chr(0x80 | Frame::OP_CONTINUATION));
        fwrite($stream, chr(0));
        rewind($stream);

        $this->expectException(WebSocketException::class);
        Frame::readMessage($stream);
    }
}
