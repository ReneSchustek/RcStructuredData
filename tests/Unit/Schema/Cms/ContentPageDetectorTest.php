<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Cms;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Cms\ContentPageDetector;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Content\Cms\CmsPageEntity;

/**
 * Prüft die eine Frage, an der `CollectionPage` und `WebPage` auseinandergehen: Führt die Seite
 * eine Liste von Einträgen — oder ist sie ein Text?
 */
class ContentPageDetectorTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    /**
     * Was: Eine Erlebniswelt vom Typ `page` ohne Listen-Baustein ist eine Inhaltsseite.
     * Warum: Impressum, AGB und Kontakt führen keine Liste — `CollectionPage` wäre dort falsch.
     */
    public function testLayoutOfTypePageIsAContentPage(): void
    {
        $detector = new ContentPageDetector(new CmsSlotIterator());
        $page = $this->makeCmsPage($this->makeSlot('text'));
        $page->setType('page');

        static::assertTrue($detector->isContentPage($this->makeContext(cmsPage: $page)));
    }

    /**
     * Was: Eine Erlebniswelt vom Typ `product_list` ist keine Inhaltsseite.
     * Warum: Sie führt genau die Liste, für die es `CollectionPage` gibt.
     */
    public function testProductListLayoutIsNoContentPage(): void
    {
        $detector = new ContentPageDetector(new CmsSlotIterator());
        $page = $this->makeCmsPage($this->makeSlot('product-listing'));
        $page->setType('product_list');

        static::assertFalse($detector->isContentPage($this->makeContext(cmsPage: $page)));
    }

    /**
     * Was: Ein `page`-Layout mit Beitragsliste bleibt eine Sammlung.
     * Warum: Die Blog-Übersicht läuft über ein `page`-Layout, führt aber eine Liste. Erkannt wird
     *        sie am Baustein, nicht am Namen der Kategorie — ein Name lässt sich ändern.
     */
    public function testBlogListingStaysACollection(): void
    {
        $detector = new ContentPageDetector(new CmsSlotIterator());
        $page = $this->makeCmsPage($this->makeSlot('text'), $this->makeSlot('blog'));
        $page->setType('page');

        static::assertFalse($detector->isContentPage($this->makeContext(cmsPage: $page)));
    }

    /**
     * Was: Ohne Erlebniswelt bleibt es beim bisherigen Verhalten.
     * Warum: Wo nichts zu unterscheiden ist, wäre eine Vermutung schlechter als der Bestand.
     */
    public function testWithoutACmsPageNothingChanges(): void
    {
        $detector = new ContentPageDetector(new CmsSlotIterator());

        static::assertFalse($detector->isContentPage($this->makeContext()));
    }

    /**
     * Was: Eine Erlebniswelt ohne gesetzten Typ ist keine Inhaltsseite.
     * Warum: `null` ist keine Aussage. Wer daraus „Inhaltsseite" macht, zeichnet im Zweifel jede
     *        Kategorie um, deren Layout gerade nicht vollständig geladen ist.
     */
    public function testLayoutWithoutTypeIsNoContentPage(): void
    {
        $detector = new ContentPageDetector(new CmsSlotIterator());
        $page = new CmsPageEntity();
        $page->setUniqueIdentifier('ohne-typ');

        static::assertFalse($detector->isContentPage($this->makeContext(cmsPage: $page)));
    }
}
