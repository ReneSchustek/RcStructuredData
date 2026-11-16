<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Product;

use Ruhrcoder\RcStructuredData\Schema\Enrichment\VideoMetadataEnricher;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * Das Video einer Produktseite als `VideoObject`, angehängt an das Produkt (`subjectOf`).
 *
 * Welches Video die Seite zeigt, entscheidet das Theme (Reiter „Video“: eigenes Feld am Artikel, sonst Elternartikel,
 * sonst Kategorie). Hier kommt nur die Kennung an. Titel, Beschreibung, Datum und Dauer liefert die
 * YouTube-Schnittstelle über denselben Schlüssel wie bei den Videos der Erlebniswelten.
 *
 * Ohne Datum gibt es keinen Knoten: Google wertet ein `VideoObject` ohne `uploadDate` als kritischen Fehler, ein
 * fehlendes dagegen gar nicht. Am Artikel gibt es kein Datumsfeld, also hängt der Knoten an der Schnittstelle; ohne
 * Schlüssel oder bei einem gelöschten Video bleibt die Seite wie zuvor.
 */
final class ProductVideoObject
{
    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    public function __construct(
        private readonly VideoMetadataEnricher $enricher,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forProduct(mixed $videoId, SalesChannelProductEntity $product): ?array
    {
        if (!\is_string($videoId) || preg_match(self::VIDEO_ID_PATTERN, $videoId) !== 1) {
            return null;
        }

        $meta = $this->enricher->enrich($videoId);
        if ($meta === null || $meta['uploadDate'] === '') {
            return null;
        }

        $productName = trim((string) ($product->getTranslation('name') ?? $product->getName() ?? ''));
        $name = $meta['name'] !== '' ? $meta['name'] : $productName;
        if ($name === '') {
            return null;
        }

        $thumbnailUrl = 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
        $node = [
            '@type' => 'VideoObject',
            'name' => $name,
            'description' => $meta['description'] !== '' ? $meta['description'] : $name,
            'thumbnailUrl' => $thumbnailUrl,
            'thumbnail' => ['@type' => 'ImageObject', 'url' => $thumbnailUrl],
            'embedUrl' => 'https://www.youtube.com/embed/' . $videoId,
            'contentUrl' => 'https://www.youtube.com/watch?v=' . $videoId,
            'uploadDate' => $meta['uploadDate'],
        ];
        if ($meta['duration'] !== '') {
            $node['duration'] = $meta['duration'];
        }

        return $node;
    }
}
