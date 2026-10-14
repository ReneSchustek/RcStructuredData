<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsImageCollector;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;

/**
 * Prüft, welche Bilder einer Erlebniswelt in den Graphen kommen: alle sichtbaren, in Seitenreihenfolge, jedes einmal.
 */
#[CoversClass(CmsImageCollector::class)]
final class CmsImageCollectorTest extends TestCase
{
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
