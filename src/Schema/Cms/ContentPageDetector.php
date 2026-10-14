<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Cms;

use Ruhrcoder\RcStructuredData\Schema\SchemaContext;

/**
 * Entscheidet, ob eine Kategorie eine Inhaltsseite ist und damit als `WebPage` gehört.
 *
 * `CollectionPage` heißt „Seite mit einer Liste von Einträgen". Impressum, AGB, Kontakt, Widerrufsrecht und
 * Datenschutzerklärung sind keine solchen Seiten; sie als `CollectionPage` auszuzeichnen wäre falsch.
 *
 * Unterschieden wird am Typ der Erlebniswelt. `category.type` ist bei allen diesen Seiten `page` und taugt nicht,
 * weil auch Produktkategorien ihn tragen. Erst `cms_page.type` trennt die Fälle: `product_list` führt eine Liste,
 * `page` nicht.
 *
 * Die Blog-Übersicht läuft ebenfalls über ein Layout vom Typ `page`, führt aber eine Liste, also ist dort
 * `CollectionPage` richtig. Erkennbar ist sie am Listen-Baustein statt am Namen der Kategorie, weil sich ein Name
 * ändern lässt und der Baustein zeigt, was die Seite tatsächlich tut.
 *
 * Landingpage-Layouts zählen als Inhaltsseite, wenn sie keine Liste führen. Im Design 2027 sind viele Seiten als
 * Landingpage angelegt, auch „Über uns" und „Unser Team", und bestehen nur aus Text und Karten. Eine Landingpage mit
 * Produktschieber, Kategoriekacheln oder Listing bleibt eine Sammlung.
 */
final class ContentPageDetector
{
    /**
     * Der Typ einer Erlebniswelt, die keine Liste führt.
     */
    private const LAYOUT_CONTENT = 'page';

    /**
     * Der Typ einer Erlebniswelt, die eine Liste führen kann, aber nicht muss.
     */
    private const LAYOUT_LANDING = 'landingpage';

    /**
     * Bausteine, die eine Liste von Einträgen ausgeben (Beiträge, Produkte, Kategorien). Steht einer davon auf der
     * Seite, ist sie trotz `page`- oder Landingpage-Layout eine Sammlung.
     */
    private const LIST_SLOT_TYPES = [
        'blog',
        'product-listing',
        'product-slider',
        'cross-selling',
        'rc-tr-showcase',
        'subcategories',
    ];

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
    ) {
    }

    public function isContentPage(SchemaContext $context): bool
    {
        $cmsPage = $context->getCmsPage();

        // Ohne Erlebniswelt lässt sich nichts unterscheiden. Die Seite bleibt dann `CollectionPage`, weil eine
        // Vermutung schlechter wäre als die Voreinstellung.
        if ($cmsPage === null) {
            return false;
        }

        // Nicht `getType()`: `CmsPageEntity::$type` ist eine typisierte Eigenschaft ohne Vorgabewert. Wurde die
        // Erlebniswelt ohne dieses Feld geladen, wirft schon der Zugriff, mitten in der Auszeichnung jeder Seite.
        // `getVars()` überspringt nicht initialisierte Eigenschaften, und der Typ ist dann `null`.
        $layout = $cmsPage->getVars()['type'] ?? null;
        if ($layout !== self::LAYOUT_CONTENT && $layout !== self::LAYOUT_LANDING) {
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
