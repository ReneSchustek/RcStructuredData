<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;

/**
 * Mitarbeiter als `Person` mit Name und Funktion, verknüpft per `worksFor` mit der Organisation.
 *
 * Quelle sind die Personenkarten des Themes: Baustein `rc-tr-service`, Darstellung `cards`, Schalter `portraits`.
 * Der Titel einer Karte ist der Name, der Text beginnt mit der Funktion; was nach dem ersten „·" folgt (Telefon,
 * E-Mail), gehört nicht dazu. Karten ohne `portraits` (etwa „Zentrale") sind keine Personen.
 *
 * **Bewusst nur Name und Funktion** (Rene, 14.10.2026): keine E-Mail, kein Telefon, kein Bild der Personen in den
 * strukturierten Daten. Ausgegeben wird nur auf der Seite, auf der die Karten stehen; eine eigene Pflege gibt es nicht.
 */
final class PersonNodeProvider implements SchemaNodeProvider
{
    private const ELEMENT_TYPE = 'rc-tr-service';

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
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
        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            if (!$this->isPeopleCards($slot)) {
                continue;
            }

            foreach ($this->items($slot) as $item) {
                $name = self::plain($item['title'] ?? null);
                if ($name === '') {
                    continue;
                }

                $node = [
                    '@type' => 'Person',
                    '@id' => $this->idFactory->person($context, \count($nodes) + 1),
                    'name' => $name,
                ];

                $jobTitle = self::jobTitle($item['text'] ?? null);
                if ($jobTitle !== '') {
                    $node['jobTitle'] = $jobTitle;
                }

                $node['worksFor'] = ['@id' => $this->idFactory->organization($context)];
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /** Die Funktion: Text der Karte ohne Auszeichnung, bis zum ersten „·". */
    public static function jobTitle(mixed $text): string
    {
        $plain = self::plain($text);
        $cut = mb_strpos($plain, '·');

        return trim($cut === false ? $plain : mb_substr($plain, 0, $cut));
    }

    private function isPeopleCards(CmsSlotEntity $slot): bool
    {
        if ($slot->getType() !== self::ELEMENT_TYPE) {
            return false;
        }

        $config = $slot->getFieldConfig();
        $display = $config->get('display')?->getValue() ?? 'cards';

        return $display === 'cards' && $config->get('portraits')?->getValue() === true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(CmsSlotEntity $slot): array
    {
        $items = $slot->getFieldConfig()->get('items')?->getValue();
        if (!\is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, '\is_array'));
    }

    private static function plain(mixed $value): string
    {
        if (!\is_string($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
