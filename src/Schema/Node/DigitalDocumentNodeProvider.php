<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Block\Documents\Struct\DocumentsStruct;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Erzeugt aus den rc-documents-Slots der Seite je Nachweis einen `DigitalDocument`-Knoten.
 *
 * Gelesen wird der aufgelöste Slot, nicht die Konfiguration. Die Adresse der Datei steht erst
 * fest, wenn der `DocumentsResolver` das Medium geladen hat; ein Eintrag, dessen Datei fehlt, ist
 * dort schon herausgefallen. Der Graph zeichnet damit genau aus, was die Seite verlinkt.
 *
 * Kein `publisher`. Ein Prüfzeugnis stellt die Prüfstelle aus, nicht der Shop. Den Shop als
 * Herausgeber einzutragen, wäre die eine Angabe, die an diesem Knoten sicher falsch ist.
 */
final class DigitalDocumentNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly DocumentsCmsBlockExtractor $extractor,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->getCmsPage() !== null;
    }

    public function provide(SchemaContext $context): array
    {
        $nodes = [];
        $index = 0;

        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            $data = $slot->getData();
            if (!$this->extractor->supports($slot) || !$data instanceof DocumentsStruct) {
                continue;
            }

            foreach ($data->getDocuments() as $document) {
                ++$index;
                $nodes[] = $this->buildNode($document, $context, $index);
            }
        }

        return $nodes;
    }

    /**
     * @param array{name: string, description: string, url: string, encodingFormat: string, datePublished: string, expires: string} $document
     *
     * @return array<string, string>
     */
    private function buildNode(array $document, SchemaContext $context, int $index): array
    {
        $node = [
            '@type' => 'DigitalDocument',
            '@id' => $this->idFactory->document($context, $index),
            'name' => $document['name'],
            'url' => $document['url'],
        ];

        // Freiwillige Angaben erscheinen nur, wenn sie gepflegt sind. Ein leeres `expires` hieße
        // für einen Leser des Graphen nicht „unbefristet", sondern „kaputt".
        foreach (['description', 'encodingFormat', 'datePublished', 'expires'] as $field) {
            if ($document[$field] !== '') {
                $node[$field] = $document[$field];
            }
        }

        return $node;
    }
}
