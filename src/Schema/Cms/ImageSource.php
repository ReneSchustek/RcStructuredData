<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Cms;

/**
 * Eine Bildquelle einer Erlebniswelt: entweder ein Medium der Medienverwaltung oder eine Bildadresse aus dem HTML eines
 * Text- oder Twig-Bausteins. Genau eines von beiden ist gesetzt; Sammler und Sitemap lesen beide Felder, ohne eine
 * Zeichenkette zerlegen zu müssen.
 */
final readonly class ImageSource
{
    private function __construct(
        public ?string $mediaId,
        public ?string $url,
    ) {
    }

    public static function fromMediaId(string $mediaId): self
    {
        return new self(strtolower($mediaId), null);
    }

    public static function fromUrl(string $url): self
    {
        return new self(null, $url);
    }

    public function isMedia(): bool
    {
        return $this->mediaId !== null;
    }

    /** Für das Entfernen von Dopplungen: Medium und Adresse können nie gleich lauten. */
    public function key(): string
    {
        return $this->mediaId ?? ('url:' . $this->url);
    }
}
