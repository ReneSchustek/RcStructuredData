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
 * Es zählt nur Sichtbares, dieselbe Regel wie beim FAQ-Schema: Auszeichnung entsteht aus dem sichtbaren Inhalt, nie
 * daneben. Abschnitte und Blöcke, die auf keinem Gerät sichtbar sind, zählen nicht; Medien anderer Typen (PDF, Video)
 * ebenso wenig.
 *
 * Drei Quellen, in Seitenreihenfolge, jedes Bild einmal:
 * 1. Medien aus der Konfiguration der Bausteine. Jeder Baustein legt seine Bilder unter `media` oder `mediaId` ab (das
 *    Video sein Vorschaubild unter `previewMedia`), auch in Listen (Galerie, Schieber, Kacheln, Karten); dazu die
 *    Hintergrundbilder von Abschnitten und Blöcken.
 * 2. Titelbilder der Produkte, die ein Baustein zeigt (Listing, Produktschieber, Querverkauf). Sie liegen nach der
 *    Auflösung der Erlebniswelt schon geladen vor.
 * 3. Bilder im HTML von Text- und Twig-Bausteinen (`<img src>`), etwa Kategoriekacheln oder Porträts, die nicht als
 *    Medium, sondern als Markup gepflegt sind.
 */
final class CmsImageCollector
{
    private const MEDIA_KEYS = ['media', 'mediaId', 'previewMedia'];

    /**
     * Obergrenze gegen ausufernde Graphen. Die bildreichste Seite, die Startseite, zeigt rund 45 Bilder; 30 reichen
     * dort nicht, 60 lässt Luft.
     */
    public const LIMIT = 60;

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
        // Gekürzt wird vor dem Laden, damit die Medienabfrage begrenzt bleibt. Was danach als Nicht-Bild herausfällt,
        // füllt nichts nach.
        $sources = \array_slice($this->sources($page), 0, self::LIMIT);
        $ids = array_values(
            array_filter(
                array_map(
                    static fn (ImageSource $source): ?string => $source->mediaId,
                    $sources
                )
            )
        );
        $media = $ids === []
            ? new MediaCollection()
            : $this->mediaRepository->search(
                new Criteria($ids),
                $context
            )->getEntities();

        $images = [];
        $seen = [];
        foreach ($sources as $source) {
            $image = $source->mediaId !== null
                ? $this->fromMedia($media->get($source->mediaId))
                : $this->fromUrl((string) $source->url, $baseUrl);
            // Ein Medium und ein `<img>` im HTML können auf dieselbe Datei zeigen; die Adresse zählt einmal.
            if ($image === null || isset($seen[$image['url']])) {
                continue;
            }
            $seen[$image['url']] = true;
            $images[] = $image;
        }

        return $images;
    }

    /**
     * Die Bildquellen der sichtbaren Teile einer Erlebniswelt, in Seitenreihenfolge, ohne Dopplung.
     *
     * `$slotOverrides` sind die Einstellungen, die eine Kategorie oder Landingpage je Baustein überschreibt
     * (`slotConfig`). Auf der Seite setzt sie der Kern beim Auflösen ein; die Sitemap liest die Erlebniswelt
     * unaufgelöst und reicht sie hier herein.
     *
     * @param array<string, mixed> $slotOverrides
     *
     * @return list<ImageSource>
     */
    public function sources(?CmsPageEntity $page, array $slotOverrides = []): array
    {
        $sources = [];
        foreach ($page?->getSections() ?? [] as $section) {
            if (!$this->isVisible($section->getVisibility())) {
                continue;
            }
            $this->addId($sources, $section->getBackgroundMediaId());

            foreach ($section->getBlocks() ?? [] as $block) {
                if (!$this->isVisible($block->getVisibility())) {
                    continue;
                }
                $this->addId($sources, $block->getBackgroundMediaId());

                foreach ($block->getSlots() ?? [] as $slot) {
                    $override = $slotOverrides[$slot->getUniqueIdentifier()] ?? null;
                    foreach ($this->fieldConfig($slot, \is_array($override) ? $override : null) as $field) {
                        $this->walk($sources, $field->getName(), $field->getValue());
                    }
                    foreach ($this->products($slot->getData()) as $product) {
                        $this->addId($sources, $product->getCover()?->getMediaId());
                    }
                }
            }
        }

        $unique = [];
        foreach ($sources as $source) {
            $unique[$source->key()] ??= $source;
        }

        return array_values($unique);
    }

    /**
     * Die Bildadressen aus HTML-Text.
     *
     * @return list<string>
     */
    public function htmlImageUrls(string $html): array
    {
        if (!str_contains($html, '<img')) {
            return [];
        }

        preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);

        return array_values(array_filter(
            array_map(
                static fn (string $src): string => trim(
                    html_entity_decode(
                        $src,
                        \ENT_QUOTES | \ENT_HTML5,
                        'UTF-8'
                    )
                ),
                $matches[1]
            ),
            static fn (string $src): bool => $src !== '' && !str_starts_with($src, 'data:'),
        ));
    }

    /**
     * Die Einstellungen eines Bausteins, mit Überschreibung. Eine Einstellung ohne `source` lässt der Kern mit einer
     * Ausnahme scheitern; für die Bildersuche zählt der Baustein dann einfach nicht.
     *
     * @param array<string, mixed>|null $override
     */
    private function fieldConfig(CmsSlotEntity $slot, ?array $override): FieldConfigCollection
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
    private function isVisible(?array $visibility): bool
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
    private function products(mixed $data): array
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
     * @param list<ImageSource> $sources
     */
    private function walk(array &$sources, string|int $key, mixed $value): void
    {
        if (\in_array($key, self::MEDIA_KEYS, true) && \is_string($value)) {
            $this->addId($sources, $value);

            return;
        }

        // Jeder andere Text kann HTML eines Text- oder Twig-Bausteins sein.
        if (\is_string($value)) {
            foreach ($this->htmlImageUrls($value) as $url) {
                $sources[] = ImageSource::fromUrl($url);
            }

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->walk($sources, $childKey, $child);
            }
        }
    }

    /**
     * @param list<ImageSource> $sources
     */
    private function addId(array &$sources, ?string $id): void
    {
        if ($id !== null && Uuid::isValid($id)) {
            $sources[] = ImageSource::fromMediaId($id);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function fromMedia(?MediaEntity $media): ?array
    {
        if ($media === null || !str_starts_with((string) $media->getMimeType(), 'image/') || $media->getUrl() === '') {
            return null;
        }

        $image = ['@type' => 'ImageObject', 'url' => $this->encodePath($media->getUrl())];
        $alt = trim((string) ($media->getTranslation('alt') ?? $media->getAlt()));
        if ($alt !== '') {
            $image['caption'] = $alt;
        }

        return $image;
    }

    /**
     * Protokollrelative Adressen bekommen `https:`, wurzelrelative die Shop-Adresse. Pfadrelative lassen sich ohne die
     * Adresse der einbettenden Seite nicht sicher auflösen und zählen nicht.
     *
     * @return array<string, string>|null
     */
    private function fromUrl(string $url, string $baseUrl): ?array
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

        return ['@type' => 'ImageObject', 'url' => $this->encodePath($url)];
    }

    /**
     * Der Pfad einer Bildadresse sauber kodiert: Dateinamen wie „T0720 ohne WZ.jpg" enthalten Leerzeichen und Umlaute,
     * die in einer URL nicht stehen dürfen. Bereits kodierte Teile bleiben, wie sie sind.
     */
    public function encodePath(string $url): string
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
