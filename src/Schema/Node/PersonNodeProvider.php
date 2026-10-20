<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\PlainText;
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
 * Ausgezeichnet werden nur Name und Funktion. E-Mail, Telefon und Bild der Personen bleiben aus den strukturierten
 * Daten draußen, auch wenn die Karte sie zeigt. Ausgegeben wird nur auf der Seite, auf der die Karten stehen; eine
 * eigene Pflege gibt es nicht.
 */
final class PersonNodeProvider implements SchemaNodeProvider
{
    private const ELEMENT_TYPE = 'rc-tr-service';

    // Felder der Elementkonfiguration und die Darstellung, für die Personen ausgezeichnet werden.
    private const FIELD_DISPLAY = 'display';
    private const FIELD_PORTRAITS = 'portraits';
    private const FIELD_ITEMS = 'items';
    private const DISPLAY_CARDS = 'cards';

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
                $name = PlainText::fromHtml($item['title'] ?? null);
                if ($name === '') {
                    continue;
                }

                // Die Nummer läuft über alle Kartenbausteine der Seite, sonst teilten sich zwei Personen eine Kennung.
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
        $plain = PlainText::fromHtml($text);
        $cut = mb_strpos($plain, '·');

        return trim($cut === false ? $plain : mb_substr($plain, 0, $cut));
    }

    private function isPeopleCards(CmsSlotEntity $slot): bool
    {
        if ($slot->getType() !== self::ELEMENT_TYPE) {
            return false;
        }

        $config = $slot->getFieldConfig();
        // Ohne gespeicherte Darstellung zeigt der Baustein Karten.
        $display = $config->get(self::FIELD_DISPLAY)?->getValue() ?? self::DISPLAY_CARDS;

        return $display === self::DISPLAY_CARDS && $config->get(self::FIELD_PORTRAITS)?->getValue() === true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(CmsSlotEntity $slot): array
    {
        $items = $slot->getFieldConfig()->get(self::FIELD_ITEMS)?->getValue();
        if (!\is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, '\is_array'));
    }

}
