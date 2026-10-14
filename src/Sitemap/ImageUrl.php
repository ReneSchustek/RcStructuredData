<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Sitemap;

use Shopware\Core\Content\Sitemap\Struct\Url;

/**
 * Ein Sitemap-Eintrag mit Bildern (`<image:image>`), sonst wie der des Kerns.
 *
 * Der Kern klont jeden Eintrag je Domäne und setzt erst dann die volle Adresse; die Bilder reisen im Klon mit. Relative
 * Bildadressen (aus HTML-Bausteinen) bekommen beim Schreiben Schema und Rechner der Seitenadresse.
 */
class ImageUrl extends Url
{
    /**
     * @var list<string>
     */
    private array $images = [];

    /**
     * @param list<string> $images
     */
    public static function from(Url $url, array $images): self
    {
        $imageUrl = new self();
        $imageUrl->setLoc($url->getLoc());
        $imageUrl->setLastmod($url->getLastmod());
        $imageUrl->setChangefreq($url->getChangefreq());
        $imageUrl->setPriority($url->getPriority());
        $imageUrl->setResource($url->getResource());
        $imageUrl->setIdentifier($url->getIdentifier());
        $imageUrl->images = $images;

        return $imageUrl;
    }

    /**
     * @return list<string>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function __toString(): string
    {
        $xml = parent::__toString();
        if ($this->images === []) {
            return $xml;
        }

        $origin = self::origin($this->getLoc());
        $images = '';
        foreach ($this->images as $image) {
            if (str_starts_with($image, '/') && !str_starts_with($image, '//')) {
                if ($origin === null) {
                    continue;
                }
                $image = $origin . $image;
            }
            $images .= '<image:image><image:loc>' . htmlspecialchars($image, \ENT_XML1 | \ENT_QUOTES, 'UTF-8') . '</image:loc></image:image>';
        }

        return substr($xml, 0, -\strlen('</url>')) . $images . '</url>';
    }

    private static function origin(string $loc): ?string
    {
        $parts = parse_url($loc);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
