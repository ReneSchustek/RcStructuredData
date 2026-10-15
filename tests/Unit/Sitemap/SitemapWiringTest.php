<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Sitemap;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Sitemap\ImageUrl;
use Ruhrcoder\RcStructuredData\Sitemap\ImageUrlProvider;
use Ruhrcoder\RcStructuredData\Sitemap\SitemapImageLoader;
use Ruhrcoder\RcStructuredData\Sitemap\SitemapImageNamespaceSubscriber;
use Shopware\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Prüft die Anbindung der Bilder an die Sitemap des Kerns: Namensraum im Wurzelelement und Lieferant, der die
 * Einträge des Kerns unverändert weiterreicht und nur um Bilder ergänzt.
 */
#[CoversClass(ImageUrlProvider::class)]
#[CoversClass(SitemapImageNamespaceSubscriber::class)]
final class SitemapWiringTest extends TestCase
{
    /**
     * Was: das Ereignis für das Wurzelelement der Sitemap.
     * Warum: Ohne den Namensraum wären die `<image:image>`-Einträge ungültiges XML.
     * Erwartet: `xmlns:image` mit der Adresse des Bild-Schemas im fertigen Wurzelelement.
     */
    public function testTheImageNamespaceIsAddedToTheRootElement(): void
    {
        $event = new SitemapFilterOpenTagEvent($this->createMock(SalesChannelContext::class));

        (new SitemapImageNamespaceSubscriber())->onOpenTag($event);

        self::assertSame(
            SitemapImageNamespaceSubscriber::NAMESPACE,
            $event->getUrlsetNamespaces()['xmlns:image'] ?? null,
        );
        self::assertArrayHasKey(SitemapFilterOpenTagEvent::class, SitemapImageNamespaceSubscriber::getSubscribedEvents());
    }

    /**
     * Was: ein Stapel des dekorierten Lieferanten mit Folge-Versatz.
     * Warum: Seiten, Reihenfolge und Blättern gehören dem Kern; der Lieferant ergänzt nur Bilder.
     * Erwartet: Name und nächster Versatz unverändert, die Einträge aus dem Bilder-Lader.
     */
    public function testTheProviderPassesTheCoreBatchThroughTheLoader(): void
    {
        $url = new Url();
        $url->setLoc('kategorie/');
        $url->setLastmod(new DateTimeImmutable('2026-10-15'));
        $url->setChangefreq('daily');
        $url->setResource('category');
        $url->setIdentifier('abc');
        $context = $this->createMock(SalesChannelContext::class);

        $inner = $this->createMock(AbstractUrlProvider::class);
        $inner->method('getName')->willReturn('category');
        $inner->expects(self::once())->method('getUrls')->with($context, 100, 200)->willReturn(new UrlResult([$url], 300));

        $withImage = ImageUrl::from($url, ['https://shop.example/media/a.webp']);
        $loader = $this->createMock(SitemapImageLoader::class);
        $loader->expects(self::once())->method('withImages')->with([$url], $context)->willReturn([$withImage]);

        $provider = new ImageUrlProvider($inner, $loader);
        $result = $provider->getUrls($context, 100, 200);

        self::assertSame('category', $provider->getName());
        self::assertSame($inner, $provider->getDecorated());
        self::assertSame(300, $result->getNextOffset());
        self::assertSame([$withImage], $result->getUrls());
    }
}
