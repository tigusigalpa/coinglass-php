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

    public function test_read_frame_unmasks_a_masked_server_frame(): void
    {
        $stream = $this->memoryStream();
        $payload = 'masked';
        $maskKey = "\x01\x02\x03\x04";
        $masked = '';

        for ($i = 0; $i < strlen($payload); $i++) {
            $masked .= $payload[$i] ^ $maskKey[$i % 4];
        }

        fwrite($stream, chr(0x80 | Frame::OP_TEXT));
        fwrite($stream, chr(0x80 | strlen($payload)) . $maskKey . $masked);
        rewind($stream);

        self::assertSame($payload, Frame::readFrame($stream)['payload']);
        fclose($stream);
    }

    public function test_read_frame_supports_a_64_bit_extended_payload_length(): void
    {
        $stream = $this->memoryStream();
        $payload = str_repeat('a', 65_536);

        fwrite($stream, chr(0x80 | Frame::OP_TEXT));
        fwrite($stream, chr(127) . pack('J', strlen($payload)) . $payload);
        rewind($stream);

        self::assertSame($payload, Frame::readFrame($stream)['payload']);
        fclose($stream);
    }

    public function test_write_handles_both_extended_length_encodings(): void
    {
        foreach ([str_repeat('a', 126), str_repeat('b', 65_536)] as $payload) {
            $stream = $this->memoryStream();
            Frame::write($stream, Frame::OP_TEXT, $payload);
            rewind($stream);

            self::assertSame($payload, Frame::readFrame($stream)['payload']);
            fclose($stream);
        }
    }

    public function test_rejects_invalid_opcodes_control_frames_and_fragment_sequences(): void
    {
        $unsupported = $this->memoryStream();
        fwrite($unsupported, chr(0x80 | 0x3) . chr(0));
        rewind($unsupported);
        try {
            Frame::readFrame($unsupported);
            self::fail('Expected an unsupported opcode to be rejected.');
        } catch (WebSocketException $exception) {
            self::assertStringContainsString('unsupported opcode', $exception->getMessage());
        }
        fclose($unsupported);

        $fragmentedControl = $this->memoryStream();
        fwrite($fragmentedControl, chr(Frame::OP_PING) . chr(0));
        rewind($fragmentedControl);
        try {
            Frame::readFrame($fragmentedControl);
            self::fail('Expected a fragmented control frame to be rejected.');
        } catch (WebSocketException $exception) {
            self::assertStringContainsString('control frame', $exception->getMessage());
        }
        fclose($fragmentedControl);

        $invalidSequence = $this->memoryStream();
        fwrite($invalidSequence, chr(Frame::OP_TEXT) . chr(1) . 'a');
        fwrite($invalidSequence, chr(0x80 | Frame::OP_TEXT) . chr(1) . 'b');
        rewind($invalidSequence);
        $this->expectException(WebSocketException::class);
        Frame::readMessage($invalidSequence);
    }
}
