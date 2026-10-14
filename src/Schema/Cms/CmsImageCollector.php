<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sammelt die Bilder, die auf einer Erlebniswelt sichtbar sind, für `image` am Seitenknoten.
 *
 * **Nur Sichtbares.** Dieselbe Regel wie beim FAQ-Schema: Auszeichnung entsteht aus dem sichtbaren Inhalt, nie daneben.
 * Abschnitte und Blöcke, die auf keinem Gerät sichtbar sind, zählen nicht; Medien anderer Typen (PDF, Video) ebenso
 * wenig.
 *
 * **Drei Quellen, in Seitenreihenfolge, jedes Bild einmal:**
 * 1. Medien aus der Konfiguration der Bausteine. Jeder Baustein legt seine Bilder unter `media` oder `mediaId` ab (das
 *    Video sein Vorschaubild unter `previewMedia`), auch
 *    in Listen (Galerie, Schieber, Kacheln, Karten); dazu die Hintergrundbilder von Abschnitten und Blöcken.
 * 2. Titelbilder der Produkte, die ein Baustein zeigt (Listing, Produktschieber, Querverkauf). Sie liegen nach der
 *    Auflösung der Erlebniswelt schon geladen vor.
 * 3. Bilder im HTML von Text- und Twig-Bausteinen (`<img src>`), etwa Kategoriekacheln oder Porträts, die nicht als
 *    Medium, sondern als Markup gepflegt sind.
 */
class CmsImageCollector
{
    private const MEDIA_KEYS = ['media', 'mediaId', 'previewMedia'];

    /**
     * Obergrenze gegen ausufernde Graphen. Die Startseite zeigt rund 45 Bilder; 30 schnitten dort ab.
     */
    private const LIMIT = 60;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    /**
     * @return list<array<string, string>>
     */
    public function collect(?CmsPageEntity $page, Context $context, string $baseUrl = ''): array
    {
        $sources = \array_slice(self::sources($page), 0, self::LIMIT);
        $ids = array_values(array_map(static fn (string $source): string => substr($source, 3), array_filter($sources, static fn (string $source): bool => str_starts_with($source, 'id:'))));
        $media = $ids === [] ? new MediaCollection() : $this->mediaRepository->search(new Criteria($ids), $context)->getEntities();

        $images = [];
        $seen = [];
        foreach ($sources as $source) {
            $image = str_starts_with($source, 'id:')
                ? self::fromMedia($media->get(substr($source, 3)))
                : self::fromUrl(substr($source, 4), $baseUrl);
            if ($image === null || isset($seen[$image['url']])) {
                continue;
            }
            $seen[$image['url']] = true;
            $images[] = $image;
        }

        return $images;
    }

    /**
     * Die Bildquellen der sichtbaren Teile einer Erlebniswelt, in Seitenreihenfolge, ohne Dopplung:
     * `id:<Medienkennung>` oder `url:<Bildadresse aus HTML>`.
     *
     * `$slotOverrides` sind die Einstellungen, die eine Kategorie oder Landingpage je Baustein überschreibt (`slotConfig`).
     * Auf der Seite setzt sie der Kern beim Auflösen ein; die Sitemap liest die Erlebniswelt unaufgelöst und reicht sie
     * hier herein.
     *
     * @param array<string, mixed> $slotOverrides
     *
     * @return list<string>
     */
    public static function sources(?CmsPageEntity $page, array $slotOverrides = []): array
    {
        $sources = [];
        foreach ($page?->getSections() ?? [] as $section) {
            if (!self::isVisible($section->getVisibility())) {
                continue;
            }
            self::addId($sources, $section->getBackgroundMediaId());

            foreach ($section->getBlocks() ?? [] as $block) {
                if (!self::isVisible($block->getVisibility())) {
                    continue;
                }
                self::addId($sources, $block->getBackgroundMediaId());

                foreach ($block->getSlots() ?? [] as $slot) {
                    $override = $slotOverrides[$slot->getUniqueIdentifier()] ?? null;
                    foreach (self::fieldConfig($slot, \is_array($override) ? $override : null) as $field) {
                        self::walk($sources, $field->getName(), $field->getValue());
                    }
                    foreach (self::products($slot->getData()) as $product) {
                        self::addId($sources, $product->getCover()?->getMediaId());
                    }
                }
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * Die Medienkennungen unter den Quellen (für Prüfungen und Aufrufer, die nur Medien brauchen).
     *
     * @return list<string>
     */
    public static function mediaIds(?CmsPageEntity $page): array
    {
        $ids = [];
        foreach (self::sources($page) as $source) {
            if (str_starts_with($source, 'id:')) {
                $ids[] = substr($source, 3);
            }
        }

        return $ids;
    }

    /**
     * Die Bildadressen aus HTML-Text.
     *
     * @return list<string>
     */
    public static function htmlImageUrls(string $html): array
    {
        if (!str_contains($html, '<img')) {
            return [];
        }

        preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);

        return array_values(array_filter(
            array_map(static fn (string $src): string => trim(html_entity_decode($src, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')), $matches[1]),
            static fn (string $src): bool => $src !== '' && !str_starts_with($src, 'data:'),
        ));
    }

    /**
     * Die Einstellungen eines Bausteins, mit Überschreibung. Eine Einstellung ohne `source` lässt der Kern mit einer
     * Ausnahme scheitern; für die Bildersuche zählt der Baustein dann einfach nicht.
     *
     * @param array<string, mixed>|null $override
     */
    private static function fieldConfig(CmsSlotEntity $slot, ?array $override): FieldConfigCollection
    {
        try {
            if ($override === null) {
                return $slot->getFieldConfig();
            }

            $config = $slot->getTranslation('config');
            $config = array_replace(\is_array($config) ? $config : [], $override);
            $collection = new FieldConfigCollection();
            foreach ($config as $key => $data) {
                if (\is_array($data) && \is_string($data['source'] ?? null)) {
                    $collection->add(new FieldConfig((string) $key, $data['source'], $data['value'] ?? null));
                }
            }

            return $collection;
        } catch (CmsException) {
            return new FieldConfigCollection();
        }
    }

    /**
     * Sichtbar, solange nicht alle Geräte ausgeschaltet sind; ohne Angabe sichtbar.
     *
     * @param array<string, bool>|null $visibility
     */
    private static function isVisible(?array $visibility): bool
    {
        if ($visibility === null || $visibility === []) {
            return true;
        }

        return \in_array(true, $visibility, true);
    }

    /**
     * Die Produkte, die ein Baustein zeigt (Listing, Schieber, Querverkauf).
     *
     * @return list<ProductEntity>
     */
    private static function products(mixed $data): array
    {
        if (!\is_object($data)) {
            return [];
        }

        $lists = [];
        if (method_exists($data, 'getListing')) {
            $lists[] = $data->getListing();
        }
        if (method_exists($data, 'getProducts')) {
            $lists[] = $data->getProducts();
        }
        if (method_exists($data, 'getCrossSellings')) {
            foreach ($data->getCrossSellings() ?? [] as $element) {
                if (\is_object($element) && method_exists($element, 'getProducts')) {
                    $lists[] = $element->getProducts();
                }
            }
        }

        $products = [];
        foreach ($lists as $list) {
            if (!is_iterable($list)) {
                continue;
            }
            foreach ($list as $product) {
                if ($product instanceof ProductEntity) {
                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    /**
     * @param list<string> $sources
     */
    private static function walk(array &$sources, string|int $key, mixed $value): void
    {
        if (\in_array($key, self::MEDIA_KEYS, true) && \is_string($value)) {
            self::addId($sources, $value);

            return;
        }

        if (\is_string($value)) {
            foreach (self::htmlImageUrls($value) as $url) {
                $sources[] = 'url:' . $url;
            }

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $childKey => $child) {
                self::walk($sources, $childKey, $child);
            }
        }
    }

    /**
     * @param list<string> $sources
     */
    private static function addId(array &$sources, ?string $id): void
    {
        if ($id !== null && Uuid::isValid($id)) {
            $sources[] = 'id:' . strtolower($id);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private static function fromMedia(?MediaEntity $media): ?array
    {
        if ($media === null || !str_starts_with((string) $media->getMimeType(), 'image/') || $media->getUrl() === '') {
            return null;
        }

        $image = ['@type' => 'ImageObject', 'url' => self::encodePath($media->getUrl())];
        $alt = trim((string) ($media->getTranslation('alt') ?? $media->getAlt()));
        if ($alt !== '') {
            $image['caption'] = $alt;
        }

        return $image;
    }

    /**
     * @return array<string, string>|null
     */
    private static function fromUrl(string $url, string $baseUrl): ?array
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            if ($baseUrl === '') {
                return null;
            }
            $url = rtrim($baseUrl, '/') . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        return ['@type' => 'ImageObject', 'url' => self::encodePath($url)];
    }

    /**
     * Der Pfad einer Bildadresse sauber kodiert: Dateinamen wie „T0720 ohne WZ.jpg" enthalten Leerzeichen und Umlaute,
     * die in einer URL nicht stehen dürfen. Bereits kodierte Teile bleiben, wie sie sind.
     */
    public static function encodePath(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['path'])) {
            return $url;
        }

        $path = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $parts['path']),
        ));

        return (isset($parts['scheme']) ? $parts['scheme'] . '://' : (str_starts_with($url, '//') ? '//' : ''))
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
