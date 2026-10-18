<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Documents;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Löst die rc-documents-Slots einer Erlebniswelt auf, die ohne Storefront geladen wurde.
 *
 * Die Diagnose-Karte lädt die Seite über das Repository, nicht über die Storefront. Ohne
 * diesen Schritt fehlten ihr die Nachweise, während die Seite sie ausliefert — die Karte meldete
 * dann eine Lücke, die es nicht gibt. Für Bilder ist das hinnehmbar (es gibt einen Rückfall), für
 * Nachweise nicht: Ohne aufgelöste Datei gibt es keinen Knoten.
 */
final class DocumentsSlotHydrator
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly DocumentsCmsBlockExtractor $extractor,
        private readonly DocumentsStructBuilder $structBuilder,
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function hydrate(?CmsPageEntity $page, Context $context): void
    {
        $slots = [];
        $mediaIds = [];
        foreach ($this->slotIterator->slots($page) as $slot) {
            if (!$this->extractor->supports($slot)) {
                continue;
            }

            $slots[] = $slot;
            array_push($mediaIds, ...$this->extractor->extractMediaIds($slot));
        }

        if ($slots === []) {
            return;
        }

        // Eine Abfrage für alle Bausteine der Seite; ohne Kennungen gar keine.
        $media = $mediaIds === []
            ? new MediaCollection()
            : $this->mediaRepository->search(new Criteria(array_values(array_unique($mediaIds))), $context)->getEntities();

        foreach ($slots as $slot) {
            $slot->setData($this->structBuilder->build($slot, $media));
        }
    }
}
