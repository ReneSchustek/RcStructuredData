<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use PHPUnit\Framework\MockObject\MockObject;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Baut SchemaContext-Instanzen für Unit-Tests ohne Kernel-Boot.
 *
 * Der SalesChannelContext wird nur für getSalesChannelId() gebraucht und daher gemockt.
 */
trait SchemaContextTrait
{
    /**
     * @param SchemaContext::TYPE_* $pageType
     * @param list<array{name: string, value: string}> $additionalProperties
     * @param array{name: string, description: string, uploadDate: string, duration: string} $videoFallback
     * @param array{datePublished?: string, author?: string, image?: string, articleBody?: string} $article
     */
    private function makeContext(
        string $pageType = SchemaContext::TYPE_CATEGORY,
        string $name = 'Fittings',
        string $description = '',
        string $pageUrl = 'https://shop.example/fittings',
        string $baseUrl = 'https://shop.example/',
        string $localeCode = 'de-DE',
        array $additionalProperties = [],
        ?CategoryEntity $category = null,
        ?CmsPageEntity $cmsPage = null,
        array $videoFallback = ['name' => '', 'description' => '', 'uploadDate' => '', 'duration' => ''],
        array $article = [],
    ): SchemaContext {
        return new SchemaContext(
            $pageType,
            $name,
            $description,
            $pageUrl,
            $baseUrl,
            $localeCode,
            $this->makeSalesChannelContext(),
            $additionalProperties,
            $category,
            $cmsPage,
            $videoFallback,
            $article,
        );
    }

    /**
     * @return SalesChannelContext&MockObject
     */
    private function makeSalesChannelContext(string $salesChannelId = 'sc-1'): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);

        return $context;
    }
}
