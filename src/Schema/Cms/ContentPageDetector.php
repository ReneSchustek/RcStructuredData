<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Cms;

use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Entscheidet, ob eine Kategorie eine **Inhaltsseite** ist und damit als `WebPage` gehört.
 *
 * **Warum es diese Frage überhaupt gibt:** `CollectionPage` heißt „Seite mit einer Liste von
 * Einträgen". Impressum, AGB, Kontakt, Widerrufsrecht und Datenschutzerklärung sind keine solchen
 * Seiten — sie als `CollectionPage` auszuzeichnen ist schlicht falsch.
 *
 * **Der Unterscheider ist der Typ der Erlebniswelt, nicht der der Kategorie.** `category.type` ist
 * bei allen diesen Seiten `page` und taugt deshalb nicht: Auch Produktkategorien tragen ihn. Erst
 * `cms_page.type` trennt die Fälle — `product_list` und `landingpage` führen eine Liste, `page`
 * nicht.
 *
 * **Die Ausnahme ist die Beitragsliste.** Die Blog-Übersicht läuft ebenfalls über ein Layout vom
 * Typ `page`, führt aber sehr wohl eine Liste — dort ist `CollectionPage` richtig. Erkennbar ist
 * sie am Listen-Baustein, nicht am Namen der Kategorie: Ein Name lässt sich ändern, ein Baustein
 * ist das, was die Seite tatsächlich tut.
 */
final class ContentPageDetector
{
    /**
     * Der Typ einer Erlebniswelt, die keine Liste führt.
     */
    private const LAYOUT_CONTENT = 'page';

    /**
     * Bausteine, die eine Liste von Einträgen ausgeben. Steht einer davon auf der Seite, ist sie
     * trotz `page`-Layout eine Sammlung.
     */
    private const LIST_SLOT_TYPES = ['blog', 'product-listing'];

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
    ) {
    }

    public function isContentPage(SchemaContext $context): bool
    {
        $cmsPage = $context->getCmsPage();

        // Ohne Erlebniswelt lässt sich nichts unterscheiden. Dann bleibt es beim bisherigen
        // Verhalten — eine Vermutung wäre hier schlechter als der Bestand.
        if ($cmsPage === null) {
            return false;
        }

        // **`getType()` wird bewusst nicht direkt gefragt.** `CmsPageEntity::$type` ist eine
        // typisierte Eigenschaft ohne Vorgabewert; wurde die Erlebniswelt ohne dieses Feld
        // geladen, wirft schon der Zugriff — und zwar mitten in der Auszeichnung jeder Seite.
        // `getVars()` überspringt nicht initialisierte Eigenschaften und liefert dann `null`.
        $layout = $cmsPage->getVars()['type'] ?? null;
        if ($layout !== self::LAYOUT_CONTENT) {
            return false;
        }

        return !$this->hasListing($context);
    }

    private function hasListing(SchemaContext $context): bool
    {
        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            if (\in_array($slot->getType(), self::LIST_SLOT_TYPES, true)) {
                return true;
            }
        }

        return false;
    }
}
