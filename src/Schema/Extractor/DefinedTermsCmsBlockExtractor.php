<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use Ruhrcoder\RcStructuredData\RcStructuredData;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Liest einen Begriffssatz aus einem rc-defined-terms-CMS-Slot.
 *
 * **Warum es diesen Baustein gibt.** Sieben Kategorieseiten tragen heute handgeschriebenes
 * JSON-LD mit einem `DefinedTermSet` — fachliches Wissen des Betriebs (Normen, Vorgaben,
 * Fachbegriffe), das sich aus keinem Produktdatensatz ableiten lässt. Fielen die Handblöcke
 * ersatzlos, ginge diese Auszeichnung verloren. Dieser Baustein holt sie ins Plugin, und zwar
 * nach derselben Regel wie alles andere hier: **Ausgezeichnet wird, was auf der Seite steht.**
 * Die Begriffe erscheinen sichtbar; das Schema ist ihr Abbild, nicht ihre Zweitfassung.
 *
 * Einzige Auswertungsstelle für rc-defined-terms: Sowohl der Storefront-Resolver (sichtbare
 * Liste) als auch der `DefinedTermSetNodeProvider` (Schema) benutzen diesen Extraktor.
 */
final class DefinedTermsCmsBlockExtractor implements CmsBlockSchemaExtractor
{
    private const NAME_KEY = 'termSetName';

    private const DESCRIPTION_KEY = 'termSetDescription';

    private const TERMS_KEY = 'terms';

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    public function supports(CmsSlotEntity $slot): bool
    {
        return $slot->getType() === RcStructuredData::CMS_ELEMENT_DEFINED_TERMS;
    }

    /**
     * @return list<array{term: string, definition: string}>
     */
    public function extract(CmsSlotEntity $slot): array
    {
        $rawTerms = $slot->getFieldConfig()->get(self::TERMS_KEY)?->getValue();
        if (!\is_array($rawTerms)) {
            return [];
        }

        $terms = [];
        foreach ($rawTerms as $rawTerm) {
            if (!\is_array($rawTerm)) {
                continue;
            }

            $term = $this->cleanText($rawTerm['term'] ?? null);
            if ($term === '') {
                continue;
            }

            // **Die Erklärung darf fehlen.** Alle sieben Sätze im Bestand tragen heute nur
            // Namen; ein `DefinedTerm` ohne Erklärung ist gültig. Ein Begriff ohne *Namen*
            // dagegen ist nichts — der fällt oben weg.
            $terms[] = [
                'term' => $term,
                'definition' => $this->cleanDefinition($rawTerm['definition'] ?? null),
            ];
        }

        return $terms;
    }

    public function extractSetName(CmsSlotEntity $slot): string
    {
        return $this->cleanText($slot->getFieldConfig()->get(self::NAME_KEY)?->getValue());
    }

    public function extractSetDescription(CmsSlotEntity $slot): string
    {
        return $this->cleanText($slot->getFieldConfig()->get(self::DESCRIPTION_KEY)?->getValue());
    }

    /**
     * Die Erklärung kommt aus dem Textbearbeiter des Backoffice und wird in der Storefront mit
     * `|raw` ausgegeben, damit Absätze und Listen erhalten bleiben.
     *
     * **Deshalb muss sie durch den Sanitizer** — dieselbe Begründung wie bei der FAQ-Antwort:
     * Ohne ihn wird aus einem Redakteursrecht ein Vollzugriff. Der Begriff selbst geht bewusst
     * nicht diesen Weg; er wird escaped ausgegeben und stünde sonst mit sichtbaren Auszeichnungen
     * auf der Seite.
     */
    private function cleanDefinition(mixed $value): string
    {
        $text = $this->cleanText($value);

        return $text === '' ? '' : trim($this->sanitizer->sanitize($text));
    }

    private function cleanText(mixed $value): string
    {
        if (!\is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
