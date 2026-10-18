<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsImageCollector;
use Ruhrcoder\RcStructuredData\Schema\Cms\ContentPageDetector;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Landingpages, die Startseite, Blog-Detailseiten und Inhaltsseiten werden als WebPage abgebildet.
 *
 * Inhaltsseiten sind Kategorien ohne Liste: Impressum, AGB, Kontakt, Widerrufsrecht,
 * Datenschutzerklärung, Anfahrt, Versand und Zahlung. Sie tragen eine Erlebniswelt vom Typ `page`.
 * Als `CollectionPage` wären sie falsch ausgezeichnet: Der Typ sagt „Seite mit einer Liste von
 * Einträgen", und eine solche Liste gibt es dort nicht.
 */
final class WebPageNodeProvider extends AbstractPageNodeProvider
{
    public function __construct(
        IdFactory $idFactory,
        private readonly ContentPageDetector $contentPageDetector,
        ?CmsImageCollector $imageCollector = null,
    ) {
        parent::__construct($idFactory, $imageCollector);
    }

    public function supports(SchemaContext $context): bool
    {
        if ($context->isLandingPage() || $context->isHome() || $context->isBlog()) {
            return true;
        }

        return $context->isCategory() && $this->contentPageDetector->isContentPage($context);
    }

    protected function pageType(): string
    {
        return 'WebPage';
    }
}
