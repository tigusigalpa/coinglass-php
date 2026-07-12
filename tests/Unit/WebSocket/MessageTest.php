<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit\WebSocket;

use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\WebSocket\Message;

final class MessageTest extends TestCase
{
    public function test_from_json_parses_a_channel_message_with_a_list_payload(): void
    {
        $json = '{"channel":"liquidation_orders","data":[{"base_asset":"BTC","exchange":"Binance","price":56738.00,"side":2,"symbol":"BTCUSDT","time":1725416318379,"volume_usd":3858.184}]}';

        $message = Message::fromJson($json);

        self::assertNotNull($message);
        self::assertSame('liquidation_orders', $message->channel);
        self::assertCount(1, $message->data);
        self::assertSame('BTC', $message->data[0]['base_asset']);
    }

    public function test_from_json_returns_null_for_non_json_payloads(): void
    {
        self::assertNull(Message::fromJson('pong'));
    }

    public function test_from_json_returns_null_when_channel_field_is_missing(): void
    {
        self::assertNull(Message::fromJson('{"foo":"bar"}'));
    }

    public function test_collection_hydrates_a_list_payload_into_dtos(): void
    {
        $message = Message::fromJson('{"channel":"futures_ticker@Binance_BTCUSDT","data":[{"exchange":"Binance","symbol":"BTCUSDT","price":62850.45}]}');

        self::assertNotNull($message);
        $collection = $message->collection();

        self::assertCount(1, $collection);
        self::assertSame('Binance', $collection->first()?->exchange);
        self::assertSame(62850.45, $collection->first()?->price);
    }

    public function test_collection_wraps_a_single_object_payload_in_a_one_item_collection(): void
    {
        $message = Message::fromJson('{"channel":"futures_ticker@Binance_BTCUSDT","data":{"exchange":"Binance","symbol":"BTCUSDT"}}');

        self::assertNotNull($message);
        $collection = $message->collection();

        self::assertCount(1, $collection);
        self::assertSame('Binance', $collection->first()?->exchange);
    }

    public function test_collection_is_empty_when_data_is_empty(): void
    {
        $message = Message::fromJson('{"channel":"liquidation_orders","data":[]}');

        self::assertNotNull($message);
        self::assertTrue($message->collection()->isEmpty());
    }
}
