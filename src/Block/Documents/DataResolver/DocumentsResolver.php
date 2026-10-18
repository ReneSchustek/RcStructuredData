<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Documents\DataResolver;

use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsStructBuilder;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Registriert das rc-documents-CMS-Element und löst die gepflegten Dateien zu Adressen auf.
 *
 * Die Medien werden über die Sammelabfrage der Erlebniswelt geladen (`collect()`), nicht einzeln.
 * So bleibt es bei einer Abfrage je Baustein, gleich wie viele Nachweise er trägt.
 */
final class DocumentsResolver extends AbstractCmsElementResolver
{
    private const RESULT_KEY_PREFIX = 'rc_documents_media_';

    public function __construct(
        private readonly DocumentsCmsBlockExtractor $extractor,
        private readonly DocumentsStructBuilder $structBuilder,
    ) {
    }

    public function getType(): string
    {
        return RcStructuredData::CMS_ELEMENT_DOCUMENTS;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaIds = $this->extractor->extractMediaIds($slot);

        // Eine Abfrage ohne Kennungen würde nicht „nichts" laden, sondern gar nicht eingeschränkt.
        if ($mediaIds === []) {
            return null;
        }

        $criteria = new CriteriaCollection();
        $criteria->add(self::RESULT_KEY_PREFIX . $slot->getUniqueIdentifier(), MediaDefinition::class, new Criteria($mediaIds));

        return $criteria;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $slot->setData($this->structBuilder->build(
            $slot,
            $result->get(self::RESULT_KEY_PREFIX . $slot->getUniqueIdentifier()),
        ));
    }
}
