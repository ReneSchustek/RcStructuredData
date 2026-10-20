<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Sitemap;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsImageCollector;
use Ruhrcoder\RcStructuredData\Schema\Cms\ImageSource;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Die Bilder je Sitemap-Eintrag, aus derselben Quelle wie `image` im Graphen.
 *
 * - Produkt: die Bilder der Galerie, das Titelbild zuerst (Varianten erben sie vom Hauptartikel).
 * - Kategorie, Startseite, Landingpage: das Kategoriebild und die Bilder der Erlebniswelt aus
 *   {@see CmsImageCollector}, mit den Überschreibungen je Baustein. Die Titelbilder der gelisteten Produkte gehören in
 *   deren eigene Einträge und fehlen hier, weil die Sitemap das Listing nicht auflöst.
 *
 * Je Stapel des Kerns (eine Seite Einträge) eine Abfrage je Art, dazu eine für die Medien der Erlebniswelten.
 */
final class SitemapImageLoader
{
    // Arten der Einträge, nach denen die Bilder geladen werden.
    private const KIND_PRODUCT = 'product';
    private const KIND_CATEGORY = 'category';
    private const KIND_LANDING = 'landing';

    // So kennzeichnet der Kern den Eintrag der Startseite (`HomeUrlProvider::getName()`).
    private const HOME_RESOURCE = 'home';

    /**
     * Dieselbe Obergrenze wie {@see CmsImageCollector}, damit ein Eintrag nicht mehr Bilder nennt als der Graph.
     */

    /**
     * @param EntityRepository<ProductCollection>     $productRepository
     * @param EntityRepository<CategoryCollection>    $categoryRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<MediaCollection>       $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $landingPageRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly CmsImageCollector $imageCollector,
    ) {
    }

    /**
     * Dieselben Einträge, jeder mit seinen Bildern, sofern er welche hat.
     *
     * @param list<Url> $urls
     *
     * @return list<Url>
     */
    public function withImages(array $urls, SalesChannelContext $context): array
    {
        $byResource = [];
        foreach ($urls as $url) {
            $id = self::entityId($url, $context);
            if ($id !== null) {
                $byResource[self::kind($url)][] = $id;
            }
        }
        if ($byResource === []) {
            return $urls;
        }

        $images = [
self::KIND_PRODUCT => $this->productImages(
    array_values(
        array_unique(
            $byResource[self::KIND_PRODUCT] ?? []
        )
    ),
    $context
),
self::KIND_CATEGORY => $this->cmsImages(
    self::KIND_CATEGORY,
    array_values(
        array_unique(
            $byResource[self::KIND_CATEGORY] ?? []
        )
    ),
    $context
),
self::KIND_LANDING => $this->cmsImages(
    self::KIND_LANDING,
    array_values(
        array_unique(
            $byResource[self::KIND_LANDING] ?? []
        )
    ),
    $context
),
        ];

        $result = [];
        foreach ($urls as $url) {
            $id = self::entityId($url, $context);
            $list = $id === null ? [] : ($images[self::kind($url)][$id] ?? []);
            $result[] = $list === [] ? $url : ImageUrl::from($url, $list);
        }

        return $result;
    }

    /**
     * Startseite und Kategorie teilen sich eine Abfrage; die Startseite ist die Navigationskategorie des Kanals.
     */
    private static function kind(Url $url): string
    {
        return match ($url->getResource()) {
            ProductEntity::class => self::KIND_PRODUCT,
            LandingPageEntity::class => self::KIND_LANDING,
            default => self::KIND_CATEGORY,
        };
    }

    /**
     * Die Kennung der Entität hinter einem Eintrag; die Startseite ist die Navigationskategorie des Kanals.
     */
    private static function entityId(Url $url, SalesChannelContext $context): ?string
    {
        $id = match ($url->getResource()) {
            ProductEntity::class, CategoryEntity::class, LandingPageEntity::class => $url->getIdentifier(),
            self::HOME_RESOURCE => $context->getSalesChannel()->getNavigationCategoryId(),
            default => null,
        };

        return \is_string($id) && Uuid::isValid($id) ? strtolower($id) : null;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, list<string>>
     */
    private function productImages(array $ids, SalesChannelContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('media.media');
        $products = $this->productRepository->search($criteria, $context->getContext())->getEntities();

        $images = [];
        foreach ($products as $product) {
            $media = $product->getMedia()?->getElements() ?? [];
            $coverId = $product->getCoverId();
            // Titelbild zuerst, danach die Galerie in ihrer gepflegten Reihenfolge.
            usort(
                $media,
                static fn (
                    ProductMediaEntity $a,
                    ProductMediaEntity $b,
                ): int => [
                    $a->getId() !== $coverId,
                    $a->getPosition(),
                ] <=> [
                    $b->getId() !== $coverId,
                    $b->getPosition(),
                ]
            );

            $urls = [];
            foreach ($media as $productMedia) {
                $url = $this->imageUrl($productMedia->getMedia());
                if ($url !== null) {
                    $urls[$url] = true;
                }
            }
            $images[$product->getId()] = \array_slice(array_keys($urls), 0, CmsImageCollector::LIMIT);
        }

        return $images;
    }

    /**
     * @param self::KIND_CATEGORY|self::KIND_LANDING $kind
     * @param list<string>         $ids
     *
     * @return array<string, list<string>>
     */
    private function cmsImages(string $kind, array $ids, SalesChannelContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('cmsPage.sections.blocks.slots');
        if ($kind === self::KIND_CATEGORY) {
            $criteria->addAssociation('media');
        }
        $entities = $kind === self::KIND_CATEGORY
            ? $this->categoryRepository->search($criteria, $context->getContext())->getEntities()
            : $this->landingPageRepository->search($criteria, $context->getContext())->getEntities();

        $sources = $this->sourcesByEntity($entities);
        $media = $this->mediaFor($sources, $context);

        $images = [];
        foreach ($sources as $id => $list) {
            $images[(string) $id] = $this->urlsOf($list, $media);
        }

        return $images;
    }

    /**
     * Die Bildquellen je Kategorie oder Landingpage, ohne Dopplung.
     *
     * Das Kategoriebild steht vor der Erlebniswelt. `slotConfig` geht mit, weil die Erlebniswelt hier unaufgelöst
     * vorliegt und niemand sonst die Überschreibungen je Baustein einsetzt.
     *
     * @param iterable<CategoryEntity|LandingPageEntity> $entities
     *
     * @return array<string, list<ImageSource>>
     */
    private function sourcesByEntity(iterable $entities): array
    {
        $sources = [];
        foreach ($entities as $entity) {
            $own = $entity instanceof CategoryEntity
                && $entity->getMediaId() !== null
                ? [ImageSource::fromMediaId($entity->getMediaId())]
                : [];
            $page = $entity->getCmsPage();
            $unique = [];
            foreach ([
                ...$own,
                ...$this->imageCollector->sources(
                    $page instanceof CmsPageEntity ? $page : null,
                    $entity->getSlotConfig() ?? []
                ),
            ] as $source) {
                $unique[$source->key()] ??= $source;
            }
            $sources[$entity->getId()] = array_values($unique);
        }

        return $sources;
    }

    /**
     * Alle Medien der Quellen in einer Abfrage.
     *
     * @param array<string, list<ImageSource>> $sources
     */
    private function mediaFor(array $sources, SalesChannelContext $context): MediaCollection
    {
        $mediaIds = [];
        foreach ($sources as $list) {
            foreach ($list as $source) {
                if ($source->mediaId !== null) {
                    $mediaIds[$source->mediaId] = true;
                }
            }
        }

        return $mediaIds === []
            ? new MediaCollection()
            : $this->mediaRepository->search(
                new Criteria(array_keys($mediaIds)),
                $context->getContext()
            )->getEntities();
    }

    /**
     * Die Bildadressen einer Seite in Seitenreihenfolge, jede einmal, höchstens so viele wie im Graphen.
     *
     * @param list<ImageSource> $sources
     *
     * @return list<string>
     */
    private function urlsOf(array $sources, MediaCollection $media): array
    {
        $urls = [];
        foreach ($sources as $source) {
            $url = $source->mediaId !== null
                ? $this->imageUrl($media->get($source->mediaId))
                : $this->htmlUrl((string) $source->url);
            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return \array_slice(array_keys($urls), 0, CmsImageCollector::LIMIT);
    }

    private function imageUrl(?MediaEntity $media): ?string
    {
        if ($media === null || !str_starts_with((string) $media->getMimeType(), 'image/') || $media->getUrl() === '') {
            return null;
        }

        return $this->imageCollector->encodePath($media->getUrl());
    }

    /**
     * Bildadressen aus HTML: absolute bleiben, wurzelrelative ergänzt der Eintrag beim Schreiben, andere zählen nicht.
     */
    private function htmlUrl(string $url): ?string
    {
        if (str_starts_with($url, '//')) {
            return $this->imageCollector->encodePath('https:' . $url);
        }
        if (str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1) {
            return $this->imageCollector->encodePath($url);
        }

        return null;
    }
}
