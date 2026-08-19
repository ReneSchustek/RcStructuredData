<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use Ruhrcoder\RcStructuredData\RcStructuredData;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Liest die Frage/Antwort-Paare aus einem rc-faq-CMS-Slot.
 *
 * Einzige Parsing-Stelle für rc-faq: sowohl der Storefront-DataResolver (sichtbares
 * Accordion) als auch der FaqPageNodeProvider (Schema) nutzen diesen Extractor — dadurch
 * sind sichtbarer Inhalt und FAQPage per Konstruktion identisch.
 */
final class FaqCmsBlockExtractor implements CmsBlockSchemaExtractor
{
    private const CONFIG_KEY = 'faqItems';

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    public function supports(CmsSlotEntity $slot): bool
    {
        return $slot->getType() === RcStructuredData::CMS_ELEMENT_FAQ;
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function extract(CmsSlotEntity $slot): array
    {
        $config = $slot->getFieldConfig()->get(self::CONFIG_KEY);
        $rawItems = $config?->getValue();
        if (!\is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!\is_array($rawItem)) {
                continue;
            }

            $question = $this->cleanText($rawItem['question'] ?? null);
            $answer = $this->cleanAnswer($rawItem['answer'] ?? null);
            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = ['question' => $question, 'answer' => $answer];
        }

        return $items;
    }

    /**
     * Die Antwort trägt gewollt Auszeichnungen — sie kommt aus dem Textbearbeiter des
     * Backoffice und wird in der Storefront mit `|raw` ausgegeben, damit Absätze und Listen
     * erhalten bleiben.
     *
     * **Deshalb muss sie durch den Sanitizer.** Ohne ihn wird aus einem Redakteursrecht ein
     * Vollzugriff: Wer `cms:update` hat, könnte Javascript einschleusen, das in der Sitzung
     * jedes Besuchers läuft — auch in der eines Administrators, der die Seite ansieht. Der
     * Sanitizer ersetzt hier das Escaping, er ergänzt es nicht.
     *
     * Die Frage geht bewusst **nicht** diesen Weg: Sie wird escaped ausgegeben und ist damit
     * ungefährlich. Durch den Sanitizer geschickt, stünden erlaubte Auszeichnungen hinterher
     * als sichtbarer Text auf der Seite.
     */
    private function cleanAnswer(mixed $value): string
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
