<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Enrichment\VideoMetadataEnricher;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Erzeugt aus den youtube-video-Slots der Seite je Video einen VideoObject-Knoten.
 *
 * Mehrere Videoblöcke werden zu mehreren VideoObject mit fortlaufender, eindeutiger `@id`
 * (#video-1, #video-2 …). Der GraphBuilder hängt sie automatisch per `hasPart` an die Seite.
 * Fehlende Metadaten werden über eine vierstufige Kaskade gefüllt: Block → Seiten-Fallback
 * (Category/Landingpage-Custom-Fields) → YouTube-Data-API (optional, nur bei gesetztem Key) →
 * Auto-Wert (Seitentitel/Meta) bzw. leer.
 */
final class VideoObjectNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly YoutubeVideoExtractor $extractor,
        private readonly IdFactory $idFactory,
        private readonly VideoMetadataEnricher $enricher,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $context->getCmsPage() !== null;
    }

    public function provide(SchemaContext $context): array
    {
        $nodes = [];
        $index = 0;

        foreach ($this->slotIterator->slots($context->getCmsPage()) as $slot) {
            if (!$this->extractor->supports($slot)) {
                continue;
            }

            foreach ($this->extractor->extract($slot) as $video) {
                $nodes[] = $this->buildNode($video, $context, ++$index);
            }
        }

        return $nodes;
    }

    /**
     * @param array{videoId: string, embedUrl: string, contentUrl: string, thumbnailUrl: string, name: string, description: string, uploadDate: string, duration: string} $video
     *
     * @return array<string, mixed>
     */
    private function buildNode(array $video, SchemaContext $context, int $index): array
    {
        $fallback = $context->getVideoFallback();
        $api = $this->resolveApiMetadata($video, $fallback);

        $node = [
            '@type' => 'VideoObject',
            '@id' => $this->idFactory->video($context, $index),
            'name' => $this->cascade($video['name'], $fallback['name'], $api['name'] ?? '', $context->getName()),
            'thumbnailUrl' => $video['thumbnailUrl'],
            'embedUrl' => $video['embedUrl'],
            'contentUrl' => $video['contentUrl'],
            'publisher' => ['@id' => $this->idFactory->organization($context)],
        ];

        $description = $this->cascade($video['description'], $fallback['description'], $api['description'] ?? '', $context->getDescription());
        if ($description !== '') {
            $node['description'] = $description;
        }

        $uploadDate = $this->cascade($video['uploadDate'], $fallback['uploadDate'], $api['uploadDate'] ?? '', '');
        if ($uploadDate !== '') {
            $node['uploadDate'] = $uploadDate;
        }

        $duration = $this->cascade($video['duration'], $fallback['duration'], $api['duration'] ?? '', '');
        if ($duration !== '') {
            $node['duration'] = $duration;
        }

        return $node;
    }

    /**
     * Ruft die API-Anreicherung nur ab, wenn nach Block + Seiten-Fallback noch ein Feld fehlt
     * (spart Quota und Latenz). Der Enricher liefert ohne gesetzten Key ohnehin null.
     *
     * @param array{videoId: string, name: string, description: string, uploadDate: string, duration: string} $video
     * @param array{name: string, description: string, uploadDate: string, duration: string} $fallback
     *
     * @return array{name: string, description: string, uploadDate: string, duration: string}|null
     */
    private function resolveApiMetadata(array $video, array $fallback): ?array
    {
        foreach (['name', 'description', 'uploadDate', 'duration'] as $field) {
            if ($video[$field] === '' && $fallback[$field] === '') {
                return $this->enricher->enrich($video['videoId']);
            }
        }

        return null;
    }

    /**
     * Erste nicht-leere Stufe gewinnt (Block → Seiten-Fallback → API → Auto-Wert).
     */
    private function cascade(string $block, string $fallback, string $api, string $auto): string
    {
        foreach ([$block, $fallback, $api] as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return $auto;
    }
}
