<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Kategorieseiten werden immer als CollectionPage abgebildet — niemals als Product.
 */
final class CollectionPageNodeProvider extends AbstractPageNodeProvider
{
    public function supports(SchemaContext $context): bool
    {
        return $context->isCategory();
    }

    protected function pageType(): string
    {
        return 'CollectionPage';
    }
}
