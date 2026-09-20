<?php

declare(strict_types=1);

namespace Tigusigalpa\CoinGlass\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Tigusigalpa\CoinGlass\Collections\CoinGlassCollection;
use Tigusigalpa\CoinGlass\Dto\CoinGlassDto;

final class DtoAndCollectionTest extends TestCase
{
    public function testDtoSupportsReadOnlyArrayAccess(): void
    {
        $dto = CoinGlassDto::fromArray(['symbol' => 'BTC', 'nullable' => null]);

        self::assertSame('BTC', $dto['symbol']);
        self::assertTrue(isset($dto['nullable']));
        self::assertNull($dto['unknown']);
    }

    public function testDtoCannotBeMutatedThroughArrayAccess(): void
    {
        $dto = CoinGlassDto::fromArray(['symbol' => 'BTC']);

        $this->expectException(LogicException::class);
        $dto['symbol'] = 'ETH';
    }

    public function testCollectionCannotBeMutatedThroughArrayAccess(): void
    {
        $collection = CoinGlassCollection::fromArray([['symbol' => 'BTC']]);

        $this->expectException(LogicException::class);
        $collection[] = CoinGlassDto::fromArray(['symbol' => 'ETH']);
    }

    public function testDtoExposesConvenienceAccessorsWithoutLosingTheRawPayload(): void
    {
        $dto = CoinGlassDto::fromArray(['symbol' => 'BTC', 'nullable' => null]);

        self::assertSame('BTC', $dto->symbol);
        self::assertTrue(isset($dto->symbol));
        self::assertFalse(isset($dto->nullable));
        self::assertSame('BTC', $dto->get('symbol'));
        self::assertSame('fallback', $dto->get('missing', 'fallback'));
        self::assertTrue($dto->has('nullable'));
        self::assertFalse($dto->has('missing'));
        self::assertSame(['symbol' => 'BTC', 'nullable' => null], $dto->toArray());
    }

    public function testDtoCannotBeUnsetThroughArrayAccess(): void
    {
        $dto = CoinGlassDto::fromArray(['symbol' => 'BTC']);

        $this->expectException(LogicException::class);
        unset($dto['symbol']);
    }

    public function testCollectionSupportsReadOperationsAndDerivedCollections(): void
    {
        $collection = CoinGlassCollection::fromArray([
            ['symbol' => 'BTC', 'price' => 1],
            ['symbol' => 'ETH', 'price' => 2],
        ]);

        self::assertFalse($collection->isEmpty());
        self::assertTrue($collection->isNotEmpty());
        self::assertSame('BTC', $collection->first()?->symbol);
        self::assertSame('ETH', $collection->last()?->symbol);
        self::assertSame('BTC', $collection[0]->symbol);
        self::assertTrue(isset($collection[1]));
        self::assertSame([1, 2], $collection->map(static fn (CoinGlassDto $dto): int => $dto->price));
        self::assertSame([['symbol' => 'ETH', 'price' => 2]], $collection->filter(static fn (CoinGlassDto $dto): bool => $dto->symbol === 'ETH')->toArray());
        self::assertSame($collection->all(), iterator_to_array($collection));
    }

    public function testCollectionReportsEmptyStateAndCannotBeUnset(): void
    {
        $collection = new CoinGlassCollection();

        self::assertNull($collection->first());
        self::assertNull($collection->last());
        self::assertTrue($collection->isEmpty());
        self::assertFalse($collection->isNotEmpty());
        self::assertFalse(isset($collection[0]));

        $this->expectException(LogicException::class);
        unset($collection[0]);
    }
}
