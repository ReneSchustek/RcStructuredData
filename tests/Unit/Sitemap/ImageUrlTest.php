<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Sitemap;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Sitemap\ImageUrl;
use Shopware\Core\Content\Sitemap\Struct\Url;

/**
 * Prüft den Sitemap-Eintrag mit Bildern.
 */
#[CoversClass(ImageUrl::class)]
final class ImageUrlTest extends TestCase
{
    /**
     * Was: Eintrag mit absoluter und wurzelrelativer Bildadresse, nach dem Klonen durch den Kern mit voller Adresse.
     * Warum: Der Kern setzt die Domäne erst am Klon; relative Bilder brauchen sie auch.
     * Erwartet: zwei `image:image` vor `</url>`, Sonderzeichen maskiert, übrige Felder wie beim Kern.
     */
    public function testImagesAreWrittenIntoTheUrl(): void
    {
        $url = new Url();
        $url->setLoc('kategorie/');
        $url->setLastmod(new DateTimeImmutable('2026-10-14'));
        $url->setChangefreq('daily');
        $url->setPriority(0.5);
        $url->setResource('category');
        $url->setIdentifier('abc');

        $clone = clone ImageUrl::from($url, ['https://cdn.example/a.jpg?x=1&y=2', '/media/b.webp']);
        $clone->setLoc('https://shop.example/kategorie/');

        static::assertSame(
            '<url><loc>https://shop.example/kategorie/</loc><lastmod>2026-10-14</lastmod><changefreq>daily</changefreq><priority>0.5</priority>'
            . '<image:image><image:loc>https://cdn.example/a.jpg?x=1&amp;y=2</image:loc></image:image>'
            . '<image:image><image:loc>https://shop.example/media/b.webp</image:loc></image:image></url>',
            (string) $clone,
        );
    }

    public function testWithoutImagesTheUrlStaysAsTheCoreWritesIt(): void
    {
        $url = new Url();
        $url->setLoc('https://shop.example/');
        $url->setLastmod(new DateTimeImmutable('2026-10-14'));
        $url->setChangefreq('daily');
        $url->setPriority(1.0);
        $url->setResource('home');
        $url->setIdentifier('');

        static::assertSame((string) $url, (string) ImageUrl::from($url, []));
    }
}
