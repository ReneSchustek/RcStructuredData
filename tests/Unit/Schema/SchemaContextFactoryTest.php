<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SchemaContextFactoryTest extends TestCase
{
    private const NAV_ROOT_ID = '0197aa00000000000000000000000000';

    public function testRootCategoryYieldsHomeContext(): void
    {
        $category = $this->makeCategory(self::NAV_ROOT_ID, name: 'Trummer Edelstahl');

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertInstanceOf(SchemaContext::class, $context);
        static::assertSame(SchemaContext::TYPE_HOME, $context->getPageType());
        static::assertTrue($context->isHome());
        static::assertSame('Trummer Edelstahl', $context->getName());
        static::assertSame('https://shop.example/', $context->getPageUrl());
        static::assertSame('https://shop.example/', $context->getBaseUrl());
        static::assertNull($context->getCategory());
        static::assertNull($context->getCmsPage());
    }

    public function testBuildsBasicCategoryContext(): void
    {
        $category = $this->makeCategory('cat-1', name: 'Fittings');

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertInstanceOf(SchemaContext::class, $context);
        static::assertSame(SchemaContext::TYPE_CATEGORY, $context->getPageType());
        static::assertSame('Fittings', $context->getName());
        static::assertSame('de-DE', $context->getLocaleCode());
        static::assertSame('https://shop.example/nav/cat-1', $context->getPageUrl());
        static::assertSame('https://shop.example/', $context->getBaseUrl());
    }

    public function testTranslationWinsOverEntityName(): void
    {
        $category = $this->makeCategory('cat-1', name: 'Fallback');
        $category->setTranslated(['name' => 'Übersetzt']);

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertSame('Übersetzt', $context?->getName());
    }

    public function testDescriptionOverrideStripsHtml(): void
    {
        $category = $this->makeCategory('cat-1', customFields: [
            'rc_schema_description' => '<p>Hochwertige <b>Fittings</b>   aus Edelstahl.</p>',
        ]);

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertSame('Hochwertige Fittings aus Edelstahl.', $context?->getDescription());
    }

    public function testDescriptionFallsBackToCategoryDescription(): void
    {
        $category = $this->makeCategory('cat-1');
        $category->setDescription('<i>Kategorie-Text</i>');

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertSame('Kategorie-Text', $context?->getDescription());
    }

    public function testAdditionalPropertiesFilterInvalidEntries(): void
    {
        $category = $this->makeCategory('cat-1', customFields: [
            'rc_schema_additional_properties' => [
                ['name' => 'Material', 'value' => 'Edelstahl'],
                'kein-array',
                ['name' => 'ohne-wert'],
                ['name' => 123, 'value' => 'ungültiger-name'],
            ],
        ]);

        $context = $this->factory()->fromCategory($category, null, $this->salesChannelContext());

        static::assertSame([['name' => 'Material', 'value' => 'Edelstahl']], $context?->getAdditionalProperties());
    }

    public function testVideoFallbackFromCustomFields(): void
    {
        $category = $this->makeCategory('cat-1', customFields: [
            'rc_schema_video_name' => ' Produktvideo ',
            'rc_schema_video_description' => 'Beschreibung',
            'rc_schema_video_upload_date' => '2024-05-01',
            'rc_schema_video_duration' => 'PT3M',
        ]);

        $fallback = $this->factory()->fromCategory($category, null, $this->salesChannelContext())?->getVideoFallback();

        static::assertNotNull($fallback);
        static::assertSame('Produktvideo', $fallback['name']);
        static::assertSame('Beschreibung', $fallback['description']);
        static::assertSame('2024-05-01', $fallback['uploadDate']);
        static::assertSame('PT3M', $fallback['duration']);
    }

    public function testForBlogBuildsBlogContext(): void
    {
        $context = $this->factory()->forBlog(
            'blog-1',
            'Edelstahl richtig montieren',
            '<p>Teaser mit <b>HTML</b></p>',
            'de-DE',
            $this->salesChannelContext(),
            ['datePublished' => '2026-05-01T10:00:00+02:00', 'author' => 'René', 'image' => '', 'articleBody' => ''],
        );

        static::assertSame(SchemaContext::TYPE_BLOG, $context->getPageType());
        static::assertTrue($context->isBlog());
        static::assertSame('Edelstahl richtig montieren', $context->getName());
        static::assertSame('Teaser mit HTML', $context->getDescription());
        static::assertSame('https://shop.example/blog/blog-1', $context->getPageUrl());
        static::assertSame('2026-05-01T10:00:00+02:00', $context->getArticle()['datePublished'] ?? null);
        static::assertNull($context->getCmsPage());
    }

    public function testFromLandingPageEntityBuildsLandingContext(): void
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId('lp-1');
        $landingPage->setName('Aktion Sommer');

        $context = $this->factory()->fromLandingPageEntity($landingPage, $this->salesChannelContext());

        static::assertSame(SchemaContext::TYPE_LANDINGPAGE, $context->getPageType());
        static::assertTrue($context->isLandingPage());
        static::assertSame('Aktion Sommer', $context->getName());
    }

    public function testForBlogPassesCmsPageThrough(): void
    {
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('cms-1');

        $context = $this->factory()->forBlog(
            'blog-1',
            'Titel',
            'Teaser',
            'de-DE',
            $this->salesChannelContext(),
            [],
            $cmsPage,
        );

        static::assertSame($cmsPage, $context->getCmsPage());
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function makeCategory(string $id, string $name = 'Kategorie', array $customFields = []): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName($name);
        if ($customFields !== []) {
            $category->setCustomFields($customFields);
        }

        return $category;
    }

    private function factory(): SchemaContextFactory
    {
        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                if ($name === 'frontend.navigation.page') {
                    return 'https://shop.example/nav/' . ($parameters['navigationId'] ?? '');
                }
                if ($name === 'werkl.frontend.blog.detail') {
                    return 'https://shop.example/blog/' . ($parameters['articleId'] ?? '');
                }

                return 'https://shop.example/';
            },
        );

        return new SchemaContextFactory($seoUrlReplacer);
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->method('getNavigationCategoryId')->willReturn(self::NAV_ROOT_ID);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));

        return $context;
    }
}
