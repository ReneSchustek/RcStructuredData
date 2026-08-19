<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\CollectionPageNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\Node\WebPageNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;

class PageNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testCollectionPageOnlySupportsCategoryPages(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory());

        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_CATEGORY)));
        static::assertFalse($provider->supports($this->makeContext(SchemaContext::TYPE_LANDINGPAGE)));
    }

    public function testWebPageSupportsLandingHomeAndBlogPages(): void
    {
        $provider = new WebPageNodeProvider(new IdFactory());

        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_LANDINGPAGE)));
        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_HOME)));
        static::assertTrue($provider->supports($this->makeContext(SchemaContext::TYPE_BLOG)));
        static::assertFalse($provider->supports($this->makeContext(SchemaContext::TYPE_CATEGORY)));
    }

    public function testBuildsCollectionPageNode(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory());
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
        $provider = new CollectionPageNodeProvider(new IdFactory());

        $node = $provider->provide($this->makeContext(description: ''))[0];
        static::assertArrayNotHasKey('description', $node);
    }

    public function testMapsAndFiltersAdditionalProperties(): void
    {
        $provider = new CollectionPageNodeProvider(new IdFactory());
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
}
