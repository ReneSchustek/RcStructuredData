<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\BreadcrumbNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;

class BreadcrumbNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testNotSupportedWithoutCategory(): void
    {
        $provider = new BreadcrumbNodeProvider(
            $this->createMock(CategoryBreadcrumbBuilder::class),
            $this->seoUrlReplacer(),
            new IdFactory(),
        );

        static::assertFalse($provider->supports($this->makeContext()));
    }

    public function testBuildsOrderedBreadcrumbList(): void
    {
        $builder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $builder->method('build')->willReturn(['c1' => 'Start', 'c2' => 'Fittings']);

        $provider = new BreadcrumbNodeProvider($builder, $this->seoUrlReplacer(), new IdFactory());
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings', category: new CategoryEntity());

        static::assertTrue($provider->supports($context));

        $node = $provider->provide($context)[0];
        static::assertSame('BreadcrumbList', $node['@type']);
        static::assertSame('https://shop.example/fittings#breadcrumb', $node['@id']);
        static::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Start', 'item' => '/seo/c1'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fittings', 'item' => '/seo/c2'],
        ], $node['itemListElement']);
    }

    public function testReturnsNothingWhenBreadcrumbEmpty(): void
    {
        $builder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $builder->method('build')->willReturn(null);

        $provider = new BreadcrumbNodeProvider($builder, $this->seoUrlReplacer(), new IdFactory());

        static::assertSame([], $provider->provide($this->makeContext(category: new CategoryEntity())));
    }

    private function seoUrlReplacer(): SeoUrlPlaceholderHandlerInterface
    {
        $replacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $replacer->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => '/seo/' . ($parameters['navigationId'] ?? ''),
        );

        return $replacer;
    }
}
