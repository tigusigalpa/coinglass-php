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
}
