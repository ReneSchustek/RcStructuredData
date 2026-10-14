<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Sitemap;

use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Hängt an die Einträge eines Sitemap-Lieferanten des Kerns die Bilder der Seite, für die Bildersuche.
 *
 * Dekoriert Produkt-, Kategorie-, Landingpage- und Startseiten-Lieferanten; Seiten und Reihenfolge bleiben, wie der
 * Kern sie liefert. Eine eigene Bilder-Sitemap neben der des Kerns gibt es nicht, weil sie ab dem ersten Tag veralten
 * würde.
 */
class ImageUrlProvider extends AbstractUrlProvider
{
    public function __construct(
        private readonly AbstractUrlProvider $decorated,
        private readonly SitemapImageLoader $loader,
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        return $this->decorated;
    }

    public function getName(): string
    {
        return $this->decorated->getName();
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $result = $this->decorated->getUrls($context, $limit, $offset);

        return new UrlResult($this->loader->withImages(array_values($result->getUrls()), $context), $result->getNextOffset());
    }
}
