<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DefinedTermsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\PlainText;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Erzeugt aus den rc-defined-terms-Slots der Seite je einen `DefinedTermSet`-Knoten.
 *
 * Je Slot entsteht ein eigener Satz, anders als bei der FAQ. Fragen einer Seite gehören
 * fachlich zusammen und werden zu *einer* `FAQPage` zusammengeführt. Begriffssätze nicht: „Normen
 * für Geländer" und „Werkstoffe" sind zwei Sammlungen mit je eigenem Namen. Sie zu verschmelzen
 * hieße, einen Namen zu verlieren, und der Name ist bei einem Begriffssatz die halbe Aussage.
 *
 * Ein Satz ohne Namen wird nicht ausgegeben. `DefinedTermSet` ohne `name` ist für einen Leser
 * wie für eine Maschine eine namenlose Liste; sie sagt nichts, kostet aber Glaubwürdigkeit. Das
 * ist dieselbe Regel wie beim VideoObject ohne Datum: lieber kein Knoten als ein unvollständiger.
 */
final class DefinedTermSetNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly DefinedTermsCmsBlockExtractor $extractor,
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
            if (!$this->extractor->supports($slot)) {
                continue;
            }

            $name = $this->extractor->extractSetName($slot);
            $terms = $this->extractor->extract($slot);
            if ($name === '' || $terms === []) {
                continue;
            }

            ++$index;

            $node = [
                '@type' => 'DefinedTermSet',
                '@id' => $this->idFactory->definedTermSet($context, $index),
                'name' => $name,
                'hasDefinedTerm' => $this->buildTerms($terms),
            ];

            $description = $this->extractor->extractSetDescription($slot);
            if ($description !== '') {
                $node['description'] = $description;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param list<array{term: string, definition: string}> $terms
     *
     * @return list<array<string, string>>
     */
    private function buildTerms(array $terms): array
    {
        $built = [];

        foreach ($terms as $term) {
            $node = ['@type' => 'DefinedTerm', 'name' => $term['term']];

            // Die Erklärung ist freiwillig; viele Begriffssätze tragen nur Namen.
            $description = PlainText::fromHtml($term['definition']);
            if ($description !== '') {
                $node['description'] = $description;
            }

            $built[] = $node;
        }

        return $built;
    }

}
