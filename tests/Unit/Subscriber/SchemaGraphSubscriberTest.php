<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CompletenessChecker;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaGraphStruct;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Ruhrcoder\RcStructuredData\Subscriber\SchemaGraphSubscriber;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prüft, wann Kategorie- und Landingpages den Graphen als Erweiterung bekommen und wann die Hinweise zur
 * Vollständigkeit mitgehen.
 */
#[CoversClass(SchemaGraphSubscriber::class)]
final class SchemaGraphSubscriberTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0190aa000000000000000000000000b1';
    private const ROOT_ID = '0190aa000000000000000000000000c0';
    private const CATEGORY_ID = '0190aa000000000000000000000000c1';

    /**
     * Was: eine Kategorieseite, Debug-Schalter aus.
     * Warum: Im Normalbetrieb geht nur der Graph an die Seite; die Hinweise sind für die Einrichtung gedacht.
     * Erwartet: Erweiterung mit dem Graphen, keine Hinweise.
     */
    public function testACategoryPageGetsTheGraphWithoutHints(): void
    {
        $page = new NavigationPage();
        $page->setCategory($this->category());

        $this->subscriber(debug: false)->onNavigationPageLoaded(new NavigationPageLoadedEvent($page, $this->context(), new Request()));

        $struct = $page->getExtension(SchemaGraphStruct::EXTENSION_NAME);
        self::assertInstanceOf(SchemaGraphStruct::class, $struct);
        self::assertSame('CollectionPage', $struct->getGraph()['@graph'][0]['@type']);
        self::assertSame([], $struct->getDiagnostics());
    }

    /**
     * Was: eine Landingpage mit eingeschaltetem Debug-Schalter im Verkaufskanal.
     * Warum: Der Schalter gilt je Kanal; eingeschaltet zeigt die Seite, was dem Graphen fehlt.
     * Erwartet: dieselben Hinweise wie der Vollständigkeitsprüfer sie für diesen Graphen liefert.
     */
    public function testALandingPageGetsHintsWhenDebugIsOn(): void
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId('0190aa000000000000000000000000d1');
        $landingPage->setName('Aktion');
        $page = new LandingPage();
        $page->setLandingPage($landingPage);

        $this->subscriber(debug: true)->onLandingPageLoaded(new LandingPageLoadedEvent($page, $this->context(), new Request()));

        $struct = $page->getExtension(SchemaGraphStruct::EXTENSION_NAME);
        self::assertInstanceOf(SchemaGraphStruct::class, $struct);
        self::assertNotSame([], $struct->getDiagnostics());
        self::assertSame((new CompletenessChecker())->describe($struct->getGraph()), $struct->getDiagnostics());
    }

    /**
     * Was: eine Navigationsseite ohne Kategorie und eine, für die kein Lieferant einen Knoten baut.
     * Warum: Ohne Kontext oder ohne Knoten gibt es keinen Graphen; eine leere Erweiterung erzeugte ein leeres Skript.
     * Erwartet: keine Erweiterung.
     */
    public function testNoExtensionWithoutContextOrNodes(): void
    {
        $withoutCategory = new NavigationPage();
        $this->subscriber(debug: false)->onNavigationPageLoaded(new NavigationPageLoadedEvent($withoutCategory, $this->context(), new Request()));

        $withoutNodes = new NavigationPage();
        $withoutNodes->setCategory($this->category());
        $this->subscriber(debug: false, nodes: [])->onNavigationPageLoaded(new NavigationPageLoadedEvent($withoutNodes, $this->context(), new Request()));

        self::assertFalse($withoutCategory->hasExtension(SchemaGraphStruct::EXTENSION_NAME));
        self::assertFalse($withoutNodes->hasExtension(SchemaGraphStruct::EXTENSION_NAME));
    }

    public function testSubscribesToCategoryAndLandingPages(): void
    {
        self::assertSame(
            [NavigationPageLoadedEvent::class, LandingPageLoadedEvent::class],
            array_keys(SchemaGraphSubscriber::getSubscribedEvents()),
        );
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function subscriber(bool $debug, array $nodes = [['@type' => 'CollectionPage', '@id' => 'https://shop.example/#page']]): SchemaGraphSubscriber
    {
        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn('https://shop.example/');

        $provider = new class ($nodes) implements SchemaNodeProvider {
            /**
             * @param list<array<string, mixed>> $nodes
             */
            public function __construct(private readonly array $nodes)
            {
            }

            public function supports(SchemaContext $context): bool
            {
                return true;
            }

            public function provide(SchemaContext $context): array
            {
                return $this->nodes;
            }
        };

        return new SchemaGraphSubscriber(
            new SchemaContextFactory($seoUrlReplacer),
            new GraphBuilder([$provider]),
            new CompletenessChecker(),
            new StaticSystemConfigService([self::SALES_CHANNEL_ID => ['RcStructuredData.config.debug' => $debug]]),
        );
    }

    private function category(): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(self::CATEGORY_ID);
        $category->setName('Handläufe');

        return $category;
    }

    private function context(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId(self::ROOT_ID);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));

        return $context;
    }
}
