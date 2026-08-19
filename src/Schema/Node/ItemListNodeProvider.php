<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\ProductListingExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;

/**
 * Erzeugt aus der Produktliste einer Kategorieseite einen ItemList-Knoten.
 *
 * Es werden nur die auf der aktuellen (paginierten) Seite gelisteten Produkte abgebildet — der von
 * Google empfohlene Weg für paginierte Sammlungen. Der Knoten steht eigenständig im @graph (keine
 * mainEntity-Verdrahtung, um mit einer evtl. vorhandenen FAQPage nicht zu kollidieren).
 */
final class ItemListNodeProvider implements SchemaNodeProvider
{
    private const DETAIL_ROUTE = 'frontend.detail.page';

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly ProductListingExtractor $extractor,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return ($context->isCategory() || $context->isLandingPage()) && $context->getCmsPage() !== null;
    }

    public function provide(SchemaContext $context): array
    {
        $productIds = $this->collectProductIds($context);
        if ($productIds === []) {
            return [];
        }

        $itemListElement = [];
        $position = 1;
        foreach ($productIds as $productId) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => $this->seoUrlReplacer->generate(self::DETAIL_ROUTE, ['productId' => $productId]),
            ];
            ++$position;
        }

        return [[
            '@type' => 'ItemList',
            '@id' => $this->idFactory->itemList($context),
            'itemListElement' => $itemListElement,
        ]];
    }

    /**
     * @return list<string>
     */
    private function collectProductIds(SchemaContext $context): array
    {
        $ids = [];
        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            if (!$this->extractor->supports($slot)) {
                continue;
            }

            foreach ($this->extractor->extract($slot) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
