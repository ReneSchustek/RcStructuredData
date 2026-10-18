<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\DefinedTerms\DataResolver;

use Ruhrcoder\RcStructuredData\Block\DefinedTerms\Struct\DefinedTermsStruct;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DefinedTermsCmsBlockExtractor;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Registriert das rc-defined-terms-CMS-Element und stellt den Begriffssatz für die Anzeige bereit.
 *
 * Die Daten liegen vollständig in der Slot-Konfiguration, deshalb ist `collect()` leer. Das
 * Auswerten teilt sich der Resolver mit dem `DefinedTermSetNodeProvider` über den Extraktor.
 * Sichtbarer Inhalt und `DefinedTermSet` stammen so aus einer Quelle.
 */
final class DefinedTermsResolver extends AbstractCmsElementResolver
{
    public function __construct(
        private readonly DefinedTermsCmsBlockExtractor $extractor,
    ) {
    }

    public function getType(): string
    {
        return RcStructuredData::CMS_ELEMENT_DEFINED_TERMS;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $slot->setData(new DefinedTermsStruct(
            $this->extractor->extractSetName($slot),
            $this->extractor->extractSetDescription($slot),
            $this->extractor->extract($slot),
        ));
    }
}
