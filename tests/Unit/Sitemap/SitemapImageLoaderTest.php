<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Sitemap;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Sitemap\ImageUrl;
use Ruhrcoder\RcStructuredData\Sitemap\SitemapImageLoader;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Prüft, welche Bilder der Lader je Sitemap-Eintrag findet: Galerie mit Titelbild zuerst, Kategoriebild vor der
 * Erlebniswelt, Überschreibungen je Baustein, Startseite über die Navigationskategorie.
 */
#[CoversClass(SitemapImageLoader::class)]
final class SitemapImageLoaderTest extends TestCase
{
    use StaticRepositoryTrait;

    private const PRODUCT = '0190aa0000000000000000000000a001';
    private const CATEGORY = '0190aa0000000000000000000000c001';
    private const HOME = '0190aa0000000000000000000000c002';
    private const LANDING = '0190aa0000000000000000000000d001';
    private const SLOT = '0190aa0000000000000000000000e001';
    private const CATEGORY_MEDIA = '0190aa0000000000000000000000f001';
    private const SLOT_MEDIA = '0190aa0000000000000000000000f002';
    private const OVERRIDE_MEDIA = '0190aa0000000000000000000000f003';

    /**
     * Was: ein Produkt, dessen Titelbild an dritter Stelle der Galerie steht, dazu ein PDF.
     * Warum: Suchmaschinen nehmen das erste Bild als Hauptbild; Nicht-Bilder gehören nicht in `<image:image>`.
     * Erwartet: Titelbild zuerst, dann die Galerie nach Position, kein PDF.
     */
    public function testProductImagesStartWithTheCoverAndSkipNonImages(): void
    {
        $product = new ProductEntity();
        $product->setId(self::PRODUCT);
        $product->setMedia(new ProductMediaCollection([
            $this->productMedia('m1', 1, '/media/eins.jpg'),
            $this->productMedia('m2', 2, '/media/zwei.jpg'),
            $this->productMedia('m3', 3, '/media/titel bild.jpg'),
            $this->productMedia('m4', 4, '/media/blatt.pdf', 'application/pdf'),
        ]));
        $product->setCoverId('m3');

        $loader = $this->loader(products: [new ProductCollection([$product])]);
        $result = $loader->withImages([$this->url(ProductEntity::class, self::PRODUCT)], $this->context());

        self::assertInstanceOf(ImageUrl::class, $result[0]);
        self::assertSame(
            ['/media/titel%20bild.jpg', '/media/eins.jpg', '/media/zwei.jpg'],
            $result[0]->getImages(),
        );
    }

    /**
     * Was: eine Kategorie mit eigenem Bild und einer Erlebniswelt, deren Bildbaustein in der Kategorie überschrieben ist.
     * Warum: Die Kategorie pflegt ihre Bausteine über `slotConfig`; das Bild der Vorlage erscheint dort nicht.
     * Erwartet: zuerst das Kategoriebild, dann das überschriebene Bild und ein absolutes Bild aus Text; das Bild der
     *           Vorlage fehlt.
     */
    public function testCategoryImagesUseTheCategoryImageAndTheSlotOverrides(): void
    {
        $category = new CategoryEntity();
        $category->setId(self::CATEGORY);
        $category->setMediaId(self::CATEGORY_MEDIA);
        $category->setCmsPage($this->page());
        $category->setSlotConfig([self::SLOT => [
            'media' => ['source' => 'static', 'value' => self::OVERRIDE_MEDIA],
            'content' => ['source' => 'static', 'value' => '<p><img src="//cdn.example/a.png"><img src="relativ.png"></p>'],
        ]]);

        $loader = $this->loader(
            categories: [new CategoryCollection([$category])],
            media: [new MediaCollection([
                $this->media(self::CATEGORY_MEDIA, '/media/kategorie.jpg'),
                $this->media(self::OVERRIDE_MEDIA, '/media/eigenes-bild.jpg'),
            ])],
        );
        $result = $loader->withImages([$this->url(CategoryEntity::class, self::CATEGORY)], $this->context());

        self::assertInstanceOf(ImageUrl::class, $result[0]);
        self::assertSame(
            ['/media/kategorie.jpg', '/media/eigenes-bild.jpg', 'https://cdn.example/a.png'],
            $result[0]->getImages(),
        );
    }

    /**
     * Was: die Startseite, eine Landingpage ohne Bilder und ein Eintrag fremder Art in einem Stapel.
     * Warum: Die Startseite hat keine eigene Kennung; sie ist die Navigationskategorie des Kanals. Einträge ohne Bilder
     *        und fremde Einträge bleiben, wie der Kern sie geliefert hat.
     * Erwartet: Startseite mit dem Bild ihrer Erlebniswelt, die beiden anderen unverändert in derselben Reihenfolge.
     */
    public function testHomeIsResolvedViaTheNavigationCategoryAndOthersStayUntouched(): void
    {
        $home = new CategoryEntity();
        $home->setId(self::HOME);
        $home->setCmsPage($this->page());

        $landing = new LandingPageEntity();
        $landing->setId(self::LANDING);

        $loader = $this->loader(
            categories: [new CategoryCollection([$home])],
            landingPages: [new LandingPageCollection([$landing])],
            media: [new MediaCollection([$this->media(self::SLOT_MEDIA, '/media/vorlage.jpg')])],
        );
        $homeUrl = $this->url('home', '');
        $landingUrl = $this->url(LandingPageEntity::class, self::LANDING);
        $otherUrl = $this->url('custom', 'keine-kennung');

        $result = $loader->withImages([$homeUrl, $landingUrl, $otherUrl], $this->context());

        self::assertInstanceOf(ImageUrl::class, $result[0]);
        self::assertSame(['/media/vorlage.jpg'], $result[0]->getImages());
        self::assertSame($landingUrl, $result[1]);
        self::assertSame($otherUrl, $result[2]);
    }

    /**
     * Was: ein Stapel nur aus Einträgen ohne Kennung.
     * Warum: Ohne Entität gibt es nichts nachzuladen.
     * Erwartet: keine Abfrage, dieselben Einträge zurück.
     */
    public function testABatchWithoutEntitiesNeedsNoQuery(): void
    {
        $url = $this->url('custom', 'x');

        self::assertSame([$url], $this->loader()->withImages([$url], $this->context()));
    }

    /**
     * @param list<ProductCollection>     $products
     * @param list<CategoryCollection>    $categories
     * @param list<LandingPageCollection> $landingPages
     * @param list<MediaCollection>       $media
     */
    private function loader(array $products = [], array $categories = [], array $landingPages = [], array $media = []): SitemapImageLoader
    {
        return new SitemapImageLoader(
            self::repository(ProductCollection::class, $products),
            self::repository(CategoryCollection::class, $categories),
            self::repository(LandingPageCollection::class, $landingPages),
            self::repository(MediaCollection::class, $media),
        );
    }

    /**
     * Eine Erlebniswelt mit einem Bildbaustein, der das Bild der Vorlage zeigt.
     */
    private function page(): CmsPageEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setId(self::SLOT);
        $slot->setSlot('image');
        $slot->setType('image');
        $slot->setConfig(['media' => ['source' => 'static', 'value' => self::SLOT_MEDIA]]);
        $slot->setTranslated(['config' => ['media' => ['source' => 'static', 'value' => self::SLOT_MEDIA]]]);

        $block = new CmsBlockEntity();
        $block->setId('block');
        $block->setSlots(new CmsSlotCollection([$slot]));

        $section = new CmsSectionEntity();
        $section->setId('section');
        $section->setBlocks(new CmsBlockCollection([$block]));

        $page = new CmsPageEntity();
        $page->setId('page');
        $page->setSections(new CmsSectionCollection([$section]));

        return $page;
    }

    private function productMedia(string $id, int $position, string $url, string $mimeType = 'image/jpeg'): ProductMediaEntity
    {
        $productMedia = new ProductMediaEntity();
        $productMedia->setId($id);
        $productMedia->setPosition($position);
        $productMedia->setMedia($this->media($id . '-media', $url, $mimeType));

        return $productMedia;
    }

    private function media(string $id, string $url, string $mimeType = 'image/jpeg'): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);
        $media->setMimeType($mimeType);
        $media->setUrl($url);

        return $media;
    }

    private function url(string $resource, string $identifier): Url
    {
        $url = new Url();
        $url->setLoc('eintrag/');
        $url->setLastmod(new DateTimeImmutable('2026-10-15'));
        $url->setChangefreq('weekly');
        $url->setResource($resource);
        $url->setIdentifier($identifier);

        return $url;
    }

    private function context(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId(self::HOME);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
