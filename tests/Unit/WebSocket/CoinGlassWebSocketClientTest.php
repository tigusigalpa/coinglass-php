<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\Exceptions\WebSocketException;
use Tigusigalpa\CoinGlass\WebSocket\CoinGlassWebSocketClient;

final class CoinGlassWebSocketClientTest extends TestCase
{
    public function testConnectCompletesAStandardsCompliantLocalHandshake(): void
    {
        $addressFile = tempnam(sys_get_temp_dir(), 'coinglass-ws-address-');
        $requestFile = tempnam(sys_get_temp_dir(), 'coinglass-ws-request-');
        $serverFile = tempnam(sys_get_temp_dir(), 'coinglass-ws-server-');
        self::assertNotFalse($addressFile);
        self::assertNotFalse($requestFile);
        self::assertNotFalse($serverFile);
        unlink($addressFile);
        unlink($requestFile);

        $serverCode = <<<'PHP'
<?php
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(1);
}

file_put_contents($argv[1], stream_socket_get_name($server, false));
$socket = stream_socket_accept($server, 3);
if ($socket === false) {
    exit(2);
}

$request = '';
while (($line = fgets($socket)) !== false) {
    $request .= $line;
    if ($line === "\r\n") {
        break;
    }
}
file_put_contents($argv[2], $request);
if (preg_match('/^Sec-WebSocket-Key:\s*(.+)\r$/mi', $request, $match) !== 1) {
    exit(3);
}

$accept = base64_encode(sha1(trim($match[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
fwrite($socket, "HTTP/1.1 101 Switching Protocols\r\n"
    . "Upgrade: WebSocket\r\n"
    . "Connection: keep-alive, Upgrade\r\n"
    . "Sec-WebSocket-Accept: {$accept}\r\n\r\n");
stream_set_timeout($socket, 1);
fread($socket, 1024);
fclose($socket);
fclose($server);
PHP;
        file_put_contents($serverFile, $serverCode);

        $process = null;
        $pipes = [];

        try {
            $process = proc_open(
                [PHP_BINARY, $serverFile, $addressFile, $requestFile],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);

            $deadline = microtime(true) + 2.0;
            while (!is_file($addressFile) && microtime(true) < $deadline) {
                usleep(10_000);
            }
            self::assertFileExists($addressFile);

            $address = trim((string) file_get_contents($addressFile));
            $client = CoinGlassWebSocketClient::make('test-key', [
                'base_url' => "ws://{$address}/ws?source=test",
                'connect_timeout' => 1.0,
            ]);
            $stream = $client->connect();

            self::assertFalse($stream->isClosed());
            $stream->close();
            self::assertTrue($stream->isClosed());

            self::assertNotFalse(stream_get_contents($pipes[1]));
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $pipes = [];
            self::assertSame(0, proc_close($process), $errors === false ? '' : $errors);
            $process = null;

            $request = (string) file_get_contents($requestFile);
            self::assertStringContainsString('GET /ws?source=test&cg-api-key=test-key HTTP/1.1', $request);
            self::assertStringContainsString("Upgrade: websocket\r\n", $request);
            self::assertStringContainsString("Connection: Upgrade\r\n", $request);
            self::assertStringContainsString("Sec-WebSocket-Version: 13\r\n", $request);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            foreach ([$addressFile, $requestFile, $serverFile] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testConnectRejectsInvalidAndUnsupportedUrlsBeforeOpeningASocket(): void
    {
        foreach (['not a websocket URL', 'http://localhost/ws'] as $baseUrl) {
            $client = CoinGlassWebSocketClient::make('test-key', ['base_url' => $baseUrl]);

            try {
                $client->connect();
                self::fail("Expected {$baseUrl} to be rejected.");
            } catch (WebSocketException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
