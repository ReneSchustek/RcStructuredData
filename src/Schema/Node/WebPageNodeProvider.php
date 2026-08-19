<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Landingpages, die Startseite und Blog-Detailseiten werden als WebPage abgebildet.
 */
final class WebPageNodeProvider extends AbstractPageNodeProvider
{
    public function supports(SchemaContext $context): bool
    {
        return $context->isLandingPage() || $context->isHome() || $context->isBlog();
    }

    protected function pageType(): string
    {
        return 'WebPage';
    }
}
