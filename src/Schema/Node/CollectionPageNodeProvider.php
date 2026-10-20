<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsImageCollector;
use Ruhrcoder\RcStructuredData\Schema\Cms\ContentPageDetector;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Kategorieseiten, die eine Liste führen, werden als CollectionPage abgebildet, niemals als
 * Product.
 *
 * Nicht jede Kategorie führt eine Liste. Impressum, AGB, Kontakt und die übrigen Inhaltsseiten
 * sind im Shop ebenfalls Kategorien, tragen aber eine Erlebniswelt vom Typ `page`. Für sie ist
 * `WebPage` richtig; die Unterscheidung trifft der ContentPageDetector.
 */
final class CollectionPageNodeProvider extends AbstractPageNodeProvider
{
    public function __construct(
        IdFactory $idFactory,
        private readonly ContentPageDetector $contentPageDetector,
        CmsImageCollector $imageCollector,
    ) {
        parent::__construct($idFactory, $imageCollector);
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->isCategory() && !$this->contentPageDetector->isContentPage($context);
    }

    protected function pageType(): string
    {
        return 'CollectionPage';
    }
}
