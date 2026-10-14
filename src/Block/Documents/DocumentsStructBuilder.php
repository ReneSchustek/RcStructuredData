<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\Documents;

use Ruhrcoder\RcStructuredData\Block\Documents\Struct\DocumentsStruct;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Setzt aus den gepflegten Einträgen eines rc-documents-Slots und den geladenen Medien die
 * Nachweisliste zusammen.
 *
 * Zwei Wege laden die Medien: die Storefront über die Sammelabfrage der Erlebniswelt
 * (`DocumentsResolver`), die Diagnose-Karte über das Medien-Repository (`DocumentsSlotHydrator`).
 * Zusammengesetzt wird an dieser einen Stelle — sonst könnten Seite und Diagnose verschiedene
 * Nachweise zählen.
 */
final class DocumentsStructBuilder
{
    public function __construct(
        private readonly DocumentsCmsBlockExtractor $extractor,
    ) {
    }

    /**
     * Die Sammelabfrage der Erlebniswelt liefert ihre Ergebnisse ungetypt; geprüft wird deshalb je
     * Eintrag, ob wirklich ein Medium zurückkam.
     *
     * @param EntityCollection<covariant Entity>|null $media
     */
    public function build(CmsSlotEntity $slot, ?EntityCollection $media): DocumentsStruct
    {
        $documents = [];
        foreach ($this->extractor->extract($slot) as $document) {
            $file = $media?->get($document['mediaId']);

            // **Gelöschte Datei, kein Eintrag.** Ein Nachweis, dessen Datei fehlt, wäre auf der
            // Seite ein toter Verweis und im Schema die Behauptung eines Belegs, den es nicht gibt.
            if (!$file instanceof MediaEntity || $file->getUrl() === '') {
                continue;
            }

            $documents[] = [
                'name' => $document['name'],
                'description' => $document['description'],
                'url' => $file->getUrl(),
                'encodingFormat' => (string) $file->getMimeType(),
                'datePublished' => $document['datePublished'],
                'expires' => $document['expires'],
            ];
        }

        return new DocumentsStruct($this->extractor->extractTitle($slot), $documents);
    }
}
