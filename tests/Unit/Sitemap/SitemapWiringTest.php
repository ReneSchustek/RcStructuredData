<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Sitemap;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Sitemap\ImageUrlProvider;
use Ruhrcoder\RcStructuredData\Sitemap\SitemapImageLoader;
use Ruhrcoder\RcStructuredData\Sitemap\SitemapImageNamespaceSubscriber;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\ProductCollection;
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
    use StaticRepositoryTrait;

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
     * Erwartet: Name und nächster Versatz unverändert; ein Eintrag ohne gültige Kennung kommt unverändert durch.
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

        $loader = new SitemapImageLoader(
            self::repository(ProductCollection::class),
            self::repository(CategoryCollection::class),
            self::repository(LandingPageCollection::class),
            self::repository(MediaCollection::class),
        );

        $provider = new ImageUrlProvider($inner, $loader);
        $result = $provider->getUrls($context, 100, 200);

        self::assertSame('category', $provider->getName());
        self::assertSame($inner, $provider->getDecorated());
        self::assertSame(300, $result->getNextOffset());
        self::assertSame([$url], $result->getUrls());
    }
}
