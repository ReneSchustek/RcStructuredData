<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\ProductListingExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\ItemListNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;

class ItemListNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testNotSupportedWithoutCmsPage(): void
    {
        static::assertFalse($this->provider()->supports($this->makeContext()));
    }

    public function testSupportsCategoryAndLandingWithCmsPage(): void
    {
        $landing = $this->makeContext(
            pageType: SchemaContext::TYPE_LANDINGPAGE,
            cmsPage: $this->makeCmsPage($this->makeProductListingSlot(['p1'])),
        );
        static::assertTrue($this->provider()->supports($landing));

        static::assertFalse($this->provider()->supports($this->makeContext(SchemaContext::TYPE_LANDINGPAGE)));
        static::assertFalse($this->provider()->supports($this->makeContext(SchemaContext::TYPE_HOME)));
    }

    public function testBuildsItemListFromListing(): void
    {
        $page = $this->makeCmsPage(
            $this->makeSlot('text'),
            $this->makeProductListingSlot(['p1', 'p2', 'p3']),
        );
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings', cmsPage: $page);

        static::assertTrue($this->provider()->supports($context));

        $node = $this->provider()->provide($context)[0];
        static::assertSame('ItemList', $node['@type']);
        static::assertSame('https://shop.example/fittings#itemlist', $node['@id']);
        static::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'url' => '/detail/p1'],
            ['@type' => 'ListItem', 'position' => 2, 'url' => '/detail/p2'],
            ['@type' => 'ListItem', 'position' => 3, 'url' => '/detail/p3'],
        ], $node['itemListElement']);
    }

    public function testReturnsNothingWithoutListingSlot(): void
    {
        $context = $this->makeContext(cmsPage: $this->makeCmsPage($this->makeSlot('text')));

        static::assertSame([], $this->provider()->provide($context));
    }

    public function testReturnsNothingWhenListingEmpty(): void
    {
        $context = $this->makeContext(cmsPage: $this->makeCmsPage($this->makeProductListingSlot([])));

        static::assertSame([], $this->provider()->provide($context));
    }

    private function provider(): ItemListNodeProvider
    {
        return new ItemListNodeProvider(
            new CmsSlotIterator(),
            new ProductListingExtractor(),
            $this->seoUrlReplacer(),
            new IdFactory(),
        );
    }

    private function seoUrlReplacer(): SeoUrlPlaceholderHandlerInterface
    {
        $replacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $replacer->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => '/detail/' . ($parameters['productId'] ?? ''),
        );

        return $replacer;
    }
}
