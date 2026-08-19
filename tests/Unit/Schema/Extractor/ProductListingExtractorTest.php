<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Extractor;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\ProductListingExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;

class ProductListingExtractorTest extends TestCase
{
    use CmsFixtureTrait;

    public function testSupportsOnlyProductListingSlots(): void
    {
        $extractor = new ProductListingExtractor();

        static::assertTrue($extractor->supports($this->makeProductListingSlot([])));
        static::assertFalse($extractor->supports($this->makeSlot('text')));
    }

    public function testExtractsProductIdsInListingOrder(): void
    {
        $extractor = new ProductListingExtractor();

        static::assertSame(
            ['p1', 'p2', 'p3'],
            $extractor->extract($this->makeProductListingSlot(['p1', 'p2', 'p3'])),
        );
    }

    public function testReturnsEmptyWhenListingEmpty(): void
    {
        static::assertSame([], (new ProductListingExtractor())->extract($this->makeProductListingSlot([])));
    }

    public function testReturnsEmptyWhenSlotHasNoListingData(): void
    {
        static::assertSame([], (new ProductListingExtractor())->extract($this->makeProductListingSlot(null)));
    }
}
