<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Cms\ContentPageDetector;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\CollectionPageNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\Node\WebPageNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;

class PageNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testCollectionPageOnlySupportsCategoryPages(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));

        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_CATEGORY)));
        static::assertFalse($provider->supports($this->makeContext(SchemaContext::TYPE_LANDINGPAGE)));
    }

    public function testWebPageSupportsLandingHomeAndBlogPages(): void
    {
        $provider = new WebPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));

        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_LANDINGPAGE)));
        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_HOME)));
        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_BLOG)));
        static::assertFalse($provider->supports($this->makeContext(SchemaContext::TYPE_CATEGORY)));
    }

    public function testBuildsCollectionPageNode(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));
        $context = $this->makeContext(
            SchemaContext::TYPE_CATEGORY,
            'Fittings',
            'Rohrverbinder',
            'https://shop.example/fittings',
        );

        $node = $provider->provide($context)[0];
        static::assertSame('CollectionPage', $node['@type']);
        static::assertSame('https://shop.example/fittings', $node['@id']);
        static::assertSame('https://shop.example/fittings', $node['url']);
        static::assertSame('Fittings', $node['name']);
        static::assertSame('Rohrverbinder', $node['description']);
        static::assertSame('de-DE', $node['inLanguage']);
    }

    public function testOmitsEmptyDescription(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));

        $node = $provider->provide($this->makeContext(description: ''))[0];
        static::assertArrayNotHasKey('description', $node);
    }

    public function testMapsAndFiltersAdditionalProperties(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));
        $context = $this->makeContext(additionalProperties: [
            ['name' => 'Material', 'value' => 'Edelstahl'],
            ['name' => '', 'value' => 'leer'],
            ['name' => 'Norm', 'value' => ''],
        ]);

        $node = $provider->provide($context)[0];
        static::assertSame([
            ['@type' => 'PropertyValue', 'name' => 'Material', 'value' => 'Edelstahl'],
        ], $node['additionalProperty']);
    }

    /**
     * Was: Eine Kategorie mit `page`-Layout wird als WebPage abgebildet, nicht als CollectionPage.
     * Warum: Impressum, AGB, Kontakt und die übrigen Inhaltsseiten sind im Shop Kategorien,
     *        führen aber keine Liste. `CollectionPage` heißt „Seite mit einer Liste von Einträgen"
     *        und wäre dort schlicht falsch.
     */
    public function testContentCategoryBecomesWebPage(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('text'));
        $page->setType('page');
        $context = $this->makeContext(SchemaContext::TYPE_CATEGORY, cmsPage: $page);

        $collection = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));
        $web = new WebPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));

        static::assertFalse($collection->supports($context));
        static::assertTrue($web->supports($context));
        static::assertSame('WebPage', $web->provide($context)[0]['@type']);
    }

    /**
     * Was: Eine Kategorie mit Produktliste bleibt CollectionPage.
     * Warum: Genau dafür gibt es den Typ — die Änderung darf den Regelfall nicht mitnehmen.
     */
    public function testProductCategoryStaysCollectionPage(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('product-listing'));
        $page->setType('product_list');
        $context = $this->makeContext(SchemaContext::TYPE_CATEGORY, cmsPage: $page);

        $collection = new CollectionPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));
        $web = new WebPageNodeProvider(new IdFactory(), new ContentPageDetector(new CmsSlotIterator()));

        static::assertTrue($collection->supports($context));
        static::assertFalse($web->supports($context));
    }

}
