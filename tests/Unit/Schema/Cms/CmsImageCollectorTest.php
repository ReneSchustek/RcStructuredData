<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsImageCollector;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Prüft, welche Bilder einer Erlebniswelt in den Graphen kommen: alle sichtbaren, in Seitenreihenfolge, jedes einmal.
 */
#[CoversClass(CmsImageCollector::class)]
final class CmsImageCollectorTest extends TestCase
{
    use StaticRepositoryTrait;

    private const A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const C = 'cccccccccccccccccccccccccccccccc';
    private const D = 'dddddddddddddddddddddddddddddddd';
    private const E = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private int $counter = 0;

    /**
     * Was: Bildbaustein, Galerie, Kacheln mit Unterlisten und ein Hintergrundbild.
     * Warum: Jeder Baustein legt Bilder unter `media` oder `mediaId` ab, auch verschachtelt.
     * Erwartet: alle Kennungen in Seitenreihenfolge, doppelte nur einmal.
     */
    public function testCollectsAllVisibleImagesInPageOrder(): void
    {
        $page = $this->page([
            $this->section(self::A, null, [
                $this->slot(['media' => self::B]),
                $this->slot(['sliderItems' => [['mediaId' => self::C], ['mediaId' => self::B]]]),
                $this->slot(['items' => [['title' => 'Karte', 'media' => self::D]]]),
            ]),
        ]);

        static::assertSame([self::A, self::B, self::C, self::D], CmsImageCollector::mediaIds($page));
    }

    /**
     * Was: Ein Block, der auf keinem Gerät sichtbar ist, und einer nur für das Telefon.
     * Warum: Auszeichnung nur aus sichtbarem Inhalt; was auf einem Gerät sichtbar ist, gilt als sichtbar.
     * Erwartet: das Bild des versteckten Blocks fehlt, das des Telefon-Blocks ist dabei.
     */
    public function testSkipsBlocksHiddenOnAllDevices(): void
    {
        $page = $this->page([
            $this->section(null, null, [$this->slot(['media' => self::D])], ['mobile' => false, 'tablet' => false, 'desktop' => false]),
            $this->section(null, null, [$this->slot(['media' => self::E])], ['mobile' => true, 'tablet' => false, 'desktop' => false]),
        ]);

        static::assertSame([self::E], CmsImageCollector::mediaIds($page));
    }

    /**
     * Was: Keine Erlebniswelt, und Werte, die keine Kennung sind.
     * Warum: Nichts zu sammeln darf keinen Fehler werfen.
     * Erwartet: leere Liste.
     */
    public function testNothingWithoutPageOrValidIds(): void
    {
        static::assertSame([], CmsImageCollector::mediaIds(null));
        static::assertSame([], CmsImageCollector::mediaIds($this->page([$this->section(null, null, [$this->slot(['media' => 'kein-bild'])])])));
    }

    /**
     * Was: Textbaustein mit Bildern im HTML (Kategoriekacheln, Porträts).
     * Warum: Diese Bilder sind als Markup gepflegt, nicht als Medium, und trotzdem sichtbar.
     * Erwartet: die Adressen in Reihenfolge, Platzhalter aus `data:` nicht.
     */
    public function testFindsImagesInHtml(): void
    {
        $page = $this->page([$this->section(null, null, [
            $this->slot(['content' => '<p>Text</p><img class="a" src="/media/a.webp" alt=""><img src=\'https://cdn.example/b.jpg\'><img src="data:image/gif;base64,AA">']),
        ])]);

        static::assertSame(['url:/media/a.webp', 'url:https://cdn.example/b.jpg'], CmsImageCollector::sources($page));
        static::assertSame([], CmsImageCollector::htmlImageUrls('<p>ohne Bild</p>'));
    }

    /**
     * Was: Bildadressen mit Leerzeichen und Umlaut, einmal roh, einmal schon kodiert.
     * Warum: Leerzeichen dürfen in einer URL nicht stehen; doppelt kodiert wäre die Adresse falsch.
     * Erwartet: einmal sauber kodiert, Abfrage bleibt.
     */
    public function testEncodesImagePaths(): void
    {
        static::assertSame(
            'https://shop.example/media/ab/T0720%20ohne%20WZ.jpg?ts=1',
            CmsImageCollector::encodePath('https://shop.example/media/ab/T0720 ohne WZ.jpg?ts=1'),
        );
        static::assertSame(
            'https://shop.example/media/Gel%C3%A4nder.png',
            CmsImageCollector::encodePath('https://shop.example/media/Gel%C3%A4nder.png'),
        );
    }

    /**
     * Was: Bildbaustein, dessen Bild eine Kategorie überschreibt (`slotConfig`).
     * Warum: Die Sitemap liest die Erlebniswelt unaufgelöst; die Überschreibung setzt dort nur der Sammler ein.
     * Erwartet: das Bild der Überschreibung statt des Layoutbildes.
     */
    public function testSlotOverridesReplaceTheLayoutImage(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-x');
        $slot->setUniqueIdentifier('slot-x');
        $slot->setSlot('image');
        $slot->setConfig(['media' => ['source' => 'static', 'value' => self::A]]);
        $slot->setTranslated(['config' => ['media' => ['source' => 'static', 'value' => self::A]]]);

        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier('block-x');
        $block->setSlots(new CmsSlotCollection([$slot]));
        $section = new CmsSectionEntity();
        $section->setUniqueIdentifier('section-x');
        $section->setBlocks(new CmsBlockCollection([$block]));
        $page = $this->page([$section]);

        static::assertSame([self::A], CmsImageCollector::mediaIds($page));
        static::assertSame(['id:' . self::B], CmsImageCollector::sources($page, ['slot-x' => ['media' => ['source' => 'static', 'value' => self::B]]]));
    }

    /**
     * Was: Medien der Erlebniswelt, ein Nicht-Bild, ein unbekanntes Medium und HTML-Bilder in allen Adressarten.
     * Warum: Der Graph nennt jedes Bild einmal, mit Bildunterschrift aus dem Alternativtext, und nur auflösbare Adressen.
     * Erwartet: eine Abfrage für die Medien; Bild mit Unterschrift, wurzelrelative Adresse mit Shop-Adresse,
     *           protokollrelative mit `https:`, absolute unverändert; PDF, unbekanntes Medium, pfadrelative Adresse und
     *           die doppelte Datei fehlen.
     */
    public function testCollectLoadsMediaOnceAndResolvesHtmlAddresses(): void
    {
        $photo = $this->media(self::A, 'https://shop.example/media/Geländer 1.jpg', 'image/jpeg', ' Geländer am Balkon ');
        $pdf = $this->media(self::B, 'https://shop.example/media/blatt.pdf', 'application/pdf');
        $page = $this->page([$this->section(null, null, [
            $this->slot(['media' => self::A]),
            $this->slot(['media' => self::B]),
            $this->slot(['media' => self::C]),
            $this->slot(['content' => '<img src="/media/a.webp"><img src="//cdn.example/b.png"><img src="relativ.png">'
                . '<img src="https://shop.example/media/Geländer 1.jpg"><img src="https://cdn.example/c.jpg">']),
        ])]);
        $repository = self::repository(MediaCollection::class, [
            static function (Criteria $criteria) use ($photo, $pdf): MediaCollection {
                self::assertSame([self::A, self::B, self::C], $criteria->getIds());

                return new MediaCollection([$photo, $pdf]);
            },
        ]);

        $images = (new CmsImageCollector($repository))->collect($page, Context::createDefaultContext(), 'https://shop.example/');

        static::assertSame([
            ['@type' => 'ImageObject', 'url' => 'https://shop.example/media/Gel%C3%A4nder%201.jpg', 'caption' => 'Geländer am Balkon'],
            ['@type' => 'ImageObject', 'url' => 'https://shop.example/media/a.webp'],
            ['@type' => 'ImageObject', 'url' => 'https://cdn.example/b.png'],
            ['@type' => 'ImageObject', 'url' => 'https://cdn.example/c.jpg'],
        ], $images);
    }

    /**
     * Was: nur HTML-Bilder, eines wurzelrelativ, ohne bekannte Shop-Adresse.
     * Warum: Ohne Medien gibt es nichts zu laden; eine wurzelrelative Adresse ohne Shop-Adresse wäre unvollständig.
     * Erwartet: keine Abfrage, nur das absolute Bild.
     */
    public function testCollectWithoutMediaNeedsNoQuery(): void
    {
        $page = $this->page([$this->section(null, null, [
            $this->slot(['content' => '<img src="/media/a.webp"><img src="https://cdn.example/c.jpg">']),
        ])]);

        $images = (new CmsImageCollector(self::repository(MediaCollection::class)))->collect($page, Context::createDefaultContext());

        static::assertSame([['@type' => 'ImageObject', 'url' => 'https://cdn.example/c.jpg']], $images);
    }

    /**
     * Was: Bausteine mit Produkten als Schieber, als Querverkauf und als Listing, dazu Unbrauchbares in den Daten.
     * Warum: Die Titelbilder der gezeigten Produkte gehören zu den Bildern der Seite.
     * Erwartet: die Titelbilder in Seitenreihenfolge, Fremdes in den Listen zählt nicht.
     */
    public function testProductCoversOfSliderCrossSellingAndListingCount(): void
    {
        $slider = new ProductSliderStruct();
        $slider->setProducts(new ProductCollection([$this->product('p1', self::A)]));

        $element = new CrossSellingElement();
        $element->setProducts(new ProductCollection([$this->product('p2', self::B)]));
        $crossSelling = new CrossSellingStruct();
        $crossSelling->setCrossSellings(new CrossSellingElementCollection([$element]));

        $listing = new class ([$this->product('p3', self::C), 'kein Produkt']) extends Struct {
            /**
             * @param list<mixed> $items
             */
            public function __construct(private readonly array $items)
            {
            }

            /**
             * @return list<mixed>
             */
            public function getListing(): array
            {
                return $this->items;
            }

            public function getProducts(): string
            {
                return 'keine Liste';
            }
        };

        $page = $this->page([$this->section(null, null, [
            $this->slotWithData($slider),
            $this->slotWithData($crossSelling),
            $this->slotWithData($listing),
        ])]);

        static::assertSame([self::A, self::B, self::C], CmsImageCollector::mediaIds($page));
    }

    /**
     * Was: ein ganz ausgeblendeter Abschnitt, eine Überschreibung ohne `source` und ein Baustein ohne Einstellung.
     * Warum: Ausgeblendetes zeigt die Seite nicht; eine unvollständige Einstellung lässt den Kern scheitern, die
     *        Bildersuche darf daran nicht abbrechen.
     * Erwartet: nur das Bild des sichtbaren Abschnitts.
     */
    public function testHiddenSectionsAndBrokenSettingsAreSkipped(): void
    {
        $hidden = $this->section(self::A, null, [$this->slot(['media' => self::B])]);
        $hidden->setVisibility(['mobile' => false, 'tablet' => false, 'desktop' => false]);

        $broken = new CmsSlotEntity();
        $broken->setUniqueIdentifier('slot-kaputt');
        $broken->setSlot('slot-kaputt');
        $broken->setTranslated(['config' => ['media' => ['value' => self::D]]]);

        $unset = new CmsSlotEntity();
        $unset->setUniqueIdentifier('slot-leer');
        $unset->setSlot('slot-leer');

        $page = $this->page([$hidden, $this->section(self::C, null, [$broken, $unset])]);

        static::assertSame(['id:' . self::C], CmsImageCollector::sources($page, ['slot-kaputt' => ['media' => ['value' => self::E]]]));
        static::assertSame([self::C], CmsImageCollector::mediaIds($page));
    }

    public function testAnAddressWithoutPathStaysAsItIs(): void
    {
        static::assertSame('https://shop.example', CmsImageCollector::encodePath('https://shop.example'));
        static::assertSame('//cdn.example/a%20b.png', CmsImageCollector::encodePath('//cdn.example/a b.png'));
    }

    private function media(string $id, string $url, string $mimeType, ?string $alt = null): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);
        $media->setUrl($url);
        $media->setMimeType($mimeType);
        if ($alt !== null) {
            $media->setAlt($alt);
        }

        return $media;
    }

    private function product(string $id, string $coverMediaId): ProductEntity
    {
        $cover = new ProductMediaEntity();
        $cover->setId($id . '-cover');
        $cover->setMediaId($coverMediaId);

        $product = new ProductEntity();
        $product->setId($id);
        $product->setCover($cover);

        return $product;
    }

    private function slotWithData(Struct $data): CmsSlotEntity
    {
        $slot = $this->slot([]);
        $slot->setData($data);

        return $slot;
    }

    /**
     * @param list<CmsSectionEntity> $sections
     */
    private function page(array $sections): CmsPageEntity
    {
        $page = new CmsPageEntity();
        $page->setSections(new CmsSectionCollection($sections));

        return $page;
    }

    /**
     * @param list<CmsSlotEntity>      $slots
     * @param array<string, bool>|null $blockVisibility
     */
    private function section(?string $background, ?string $blockBackground, array $slots, ?array $blockVisibility = null): CmsSectionEntity
    {
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier('block-' . ++$this->counter);
        if ($blockBackground !== null) {
            $block->setBackgroundMediaId($blockBackground);
        }
        if ($blockVisibility !== null) {
            $block->setVisibility($blockVisibility);
        }
        $block->setSlots(new CmsSlotCollection($slots));

        $section = new CmsSectionEntity();
        $section->setUniqueIdentifier('section-' . ++$this->counter);
        if ($background !== null) {
            $section->setBackgroundMediaId($background);
        }
        $section->setBlocks(new CmsBlockCollection([$block]));

        return $section;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-' . ++$this->counter);
        $slot->setSlot('slot-' . $this->counter);
        $fields = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $fields->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($fields);

        return $slot;
    }
}
