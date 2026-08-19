<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Enrichment;

/**
 * Optionale Zusatzquelle für Video-Metadaten in der Feld-Kaskade des VideoObjectNodeProvider.
 *
 * Wird zwischen Category-Fallback und Auto-Wert eingehängt (Block → Category → Enricher → Auto).
 * Liefert `null`, wenn keine Anreicherung möglich ist (kein Key, Fehler, Video nicht gefunden) —
 * die Kaskade fällt dann unverändert auf ihren Auto-Wert zurück (DIP; testbar ohne Netzwerk).
 */
interface VideoMetadataEnricher
{
    /**
     * @return array{name: string, description: string, uploadDate: string, duration: string}|null
     */
    public function enrich(string $videoId): ?array;
}
