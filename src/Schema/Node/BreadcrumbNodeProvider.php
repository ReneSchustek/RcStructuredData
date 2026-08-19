<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;

/**
 * Erzeugt die BreadcrumbList automatisch aus der Shopware-Navigation.
 *
 * Der Core-CategoryBreadcrumbBuilder liefert den am Sales-Channel-Einstiegspunkt
 * beschnittenen Pfad (Kategorie-ID => Name); je Eintrag wird ein SEO-URL-Platzhalter
 * erzeugt, der im Response zur absoluten URL aufgelöst wird.
 */
final class BreadcrumbNodeProvider implements SchemaNodeProvider
{
    private const NAVIGATION_ROUTE = 'frontend.navigation.page';

    public function __construct(
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->getCategory() !== null;
    }

    public function provide(SchemaContext $context): array
    {
        $category = $context->getCategory();
        if ($category === null) {
            return [];
        }

        $salesChannel = $context->getSalesChannelContext()->getSalesChannel();
        $breadcrumb = $this->breadcrumbBuilder->build($category, $salesChannel);

        if ($breadcrumb === null || $breadcrumb === []) {
            return [];
        }

        $itemListElement = [];
        $position = 1;
        foreach ($breadcrumb as $categoryId => $name) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $name,
                'item' => $this->seoUrlReplacer->generate(
                    self::NAVIGATION_ROUTE,
                    ['navigationId' => $categoryId],
                ),
            ];
            ++$position;
        }

        return [[
            '@type' => 'BreadcrumbList',
            '@id' => $this->idFactory->breadcrumb($context),
            'itemListElement' => $itemListElement,
        ]];
    }
}
