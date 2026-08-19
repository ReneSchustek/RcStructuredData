<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use Ruhrcoder\RcStructuredData\RcStructuredData;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;

/**
 * Meldet zwei Zustände, die eine Seite still falsch auszeichnen.
 *
 * Der CompletenessChecker sieht nur den Graphen, den dieses Plugin selbst baut. Beide Fehler
 * unten stehen aber im Verhältnis zwischen Graph und Seite und sind dort nicht zu sehen:
 *
 * 1. **Handgeschriebenes JSON-LD auf derselben Seite.** Steht in einem Slot ein eigener
 *    `ld+json`-Block, trägt die Seite zwei Auszeichnungen. Sie können auseinanderlaufen, und
 *    genau das ist auf den Blogbeiträgen passiert: zwanzig Fragen und fünf HowTo-Schritte, von
 *    denen keiner auf der Seite sichtbar war — ein Richtlinienverstoß, den niemand bemerkt hat.
 * 2. **`FAQPage` ohne einen FAQ-Baustein.** Der Graph entsteht aus dem sichtbaren Seiteninhalt.
 *    Meldet er trotzdem eine FAQ, ohne dass ein `rc-faq`-Baustein auf der Seite steht, stammt
 *    sie aus einer anderen Quelle — der Verdacht auf eine FAQ, die kein Besucher sieht.
 *
 * Beide Prüfungen kosten nichts: Sie laufen über die ohnehin geladenen Slots.
 */
final class PageWarningChecker
{
    public const WARNING_HANDWRITTEN_JSON_LD = 'handwritten_json_ld';
    public const WARNING_FAQ_WITHOUT_BLOCK = 'faq_without_block';

    /**
     * Bewusst nicht auf `moorl-twig` eingegrenzt: Handgeschriebenes JSON-LD kann in jedem Slot
     * stehen, der freies Markup zulässt. Gemeldet wird deshalb der Slot-Typ, in dem es steckt.
     */
    private const JSON_LD_MARKER = 'application/ld+json';

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
    ) {
    }

    /**
     * @param array<string, mixed>|null $graph
     *
     * @return list<array{code: string, message: string}>
     */
    public function check(?array $graph, ?CmsPageEntity $page): array
    {
        $slots = $this->slotIterator->slots($page);

        $warnings = [];

        $slotTypes = $this->slotTypesWithHandwrittenJsonLd($slots);
        if ($slotTypes !== []) {
            $warnings[] = [
                'code' => self::WARNING_HANDWRITTEN_JSON_LD,
                'message' => sprintf(
                    'Auf dieser Seite steht handgeschriebenes JSON-LD (%s). Zusammen mit dem erzeugten '
                    . 'Graphen trägt die Seite zwei Auszeichnungen, die auseinanderlaufen können.',
                    implode(', ', $slotTypes)
                ),
            ];
        }

        if ($this->graphHasFaqPage($graph) && !$this->hasFaqBlock($slots)) {
            $warnings[] = [
                'code' => self::WARNING_FAQ_WITHOUT_BLOCK,
                'message' => 'Diese Seite meldet FAQPage, ohne einen rc-faq-Baustein zu haben. '
                    . 'Die Fragen stammen dann nicht aus dem sichtbaren Seiteninhalt.',
            ];
        }

        return $warnings;
    }

    /**
     * @param list<CmsSlotEntity> $slots
     *
     * @return list<string>
     */
    private function slotTypesWithHandwrittenJsonLd(array $slots): array
    {
        $types = [];

        foreach ($slots as $slot) {
            if (!$this->containsJsonLd($slot)) {
                continue;
            }

            $type = $slot->getType();
            if (!\in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Gesucht wird in beiden Ablagen derselben Sache: `config` trägt die rohen Werte, wie sie in
     * der Datenbank stehen, `fieldConfig` die aufgelösten. Je nachdem, wie der Slot geladen wurde,
     * ist die eine oder die andere gefüllt — geprüft werden deshalb beide.
     */
    private function containsJsonLd(CmsSlotEntity $slot): bool
    {
        $config = $slot->getConfig();
        if (\is_array($config) && $this->encodedContains($config)) {
            return true;
        }

        $fieldConfig = $slot->getFieldConfig();

        foreach ($fieldConfig->getElements() as $field) {
            if ($this->encodedContains($field->getValue())) {
                return true;
            }
        }

        return false;
    }

    /**
     * `JSON_UNESCAPED_SLASHES` ist hier nicht Geschmack: Ohne die Angabe schreibt `json_encode`
     * den Schrägstrich als `\/`, und die Suche nach `application/ld+json` findet nie etwas.
     */
    private function encodedContains(mixed $value): bool
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

        return \is_string($encoded) && str_contains($encoded, self::JSON_LD_MARKER);
    }

    /**
     * @param array<string, mixed>|null $graph
     */
    private function graphHasFaqPage(?array $graph): bool
    {
        $nodes = $graph['@graph'] ?? null;
        if (!\is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!\is_array($node)) {
                continue;
            }

            // `@type` darf laut JSON-LD auch eine Liste sein.
            $type = $node['@type'] ?? null;
            $types = \is_array($type) ? $type : [$type];

            if (\in_array('FAQPage', $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<CmsSlotEntity> $slots
     */
    private function hasFaqBlock(array $slots): bool
    {
        foreach ($slots as $slot) {
            if ($slot->getType() === RcStructuredData::CMS_ELEMENT_FAQ) {
                return true;
            }
        }

        return false;
    }
}
