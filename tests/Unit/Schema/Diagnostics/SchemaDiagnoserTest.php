<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsSlotHydrator;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsStructBuilder;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CompletenessChecker;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\DiagnosisChannelResolver;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\DiagnosisPageLoader;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\PageWarningChecker;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\SchemaDiagnoser;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use RuntimeException;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Prüft die Diagnose einer Kategorie oder Landingpage ohne Storefront-Aufruf: Kanalwahl, Suche nach Name oder Kennung,
 * Erlebniswelt und Ergebnis.
 */
#[CoversClass(SchemaDiagnoser::class)]
final class SchemaDiagnoserTest extends TestCase
{
    use StaticRepositoryTrait;

    private const SALES_CHANNEL_ID = '0190aa000000000000000000000000b1';
    private const LANGUAGE_ID = '0190aa000000000000000000000000a9';
    private const ROOT_ID = '0190aa000000000000000000000000c0';
    private const CATEGORY_ID = '0190aa000000000000000000000000c1';
    private const CMS_PAGE_ID = '0190aa000000000000000000000000e1';
    private const LANDING_ID = '0190aa000000000000000000000000d1';

    /**
     * Was: Diagnose einer Kategorie über ihren Namen, mit Erlebniswelt.
     * Warum: Der Befehl wird mit dem Namen aufgerufen, den der Händler kennt; der Kontext braucht die Standardsprache
     * des
     *        Kanals, nicht die des Systems.
     * Erwartet: Suche nach dem Namen, Kanal mit seiner Sprache, Graph und Hinweise im Ergebnis.
     */
    public function testACategoryIsFoundByNameAndAnalysed(): void
    {
        $category = $this->category();
        $category->setCmsPageId(self::CMS_PAGE_ID);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId(self::CMS_PAGE_ID);

        $categories = self::repository(CategoryCollection::class, [
            static function (Criteria $criteria) use ($category): CategoryCollection {
                self::assertEquals([new EqualsFilter('name', 'Handläufe')], $criteria->getFilters());

                return new CategoryCollection([$category]);
            },
        ]);
        $contextFactory = $this->contextFactory();
        $contextFactory->expects(self::once())->method('create')->with(
            self::isType('string'),
            self::SALES_CHANNEL_ID,
            [SalesChannelContextService::LANGUAGE_ID => self::LANGUAGE_ID],
        );

        $result = $this->diagnoser(
            categories: $categories,
            cmsPages: self::repository(CmsPageCollection::class, [new CmsPageCollection([$cmsPage])]),
            contextFactory: $contextFactory,
        )->diagnose('Handläufe');

        self::assertTrue($result->pageFound);
        self::assertSame('Handläufe', $result->pageName);
        self::assertNotNull($result->graph);
        self::assertSame(1, $result->nodeCount());
        self::assertSame((new CompletenessChecker())->check($result->graph), $result->findings);
    }

    /**
     * Was: kein Storefront-Kanal, aber ein anderer aktiver Kanal; die Kategorie per Kennung, ohne Erlebniswelt.
     * Warum: Auch ein Shop ohne Storefront-Kanal (etwa nur mit Schnittstelle) soll sich prüfen lassen.
     * Erwartet: der zweite Kanal wird genommen, die Kategorie über den Primärschlüssel gefunden.
     */
    public function testFallsBackToAnyActiveChannelAndFindsCategoriesById(): void
    {
        $channels = self::repository(
            SalesChannelCollection::class,
            [
                new SalesChannelCollection(),
                new SalesChannelCollection([$this->salesChannel()]),
            ]
        );
        $categories = self::repository(CategoryCollection::class, [
            function (Criteria $criteria): CategoryCollection {
                self::assertSame([self::CATEGORY_ID], $criteria->getIds());
                self::assertSame([], $criteria->getFilters());

                return new CategoryCollection([$this->category()]);
            },
        ]);

        $result = $this->diagnoser(categories: $categories, channels: $channels)->diagnose(self::CATEGORY_ID);

        self::assertTrue($result->pageFound);
        self::assertSame([], $channels->searches, 'Beide Kanalabfragen gestellt');
    }

    /**
     * Ein genannter Kanal wird über seine Kennung gesucht und gilt nur, wenn er aktiv ist; in einem Shop mit mehreren
     * Kanälen unterscheiden sich URLs, Sprache und Einstellungen.
     */
    public function testANamedChannelIsUsedWhenActive(): void
    {
        $channelId = Uuid::randomHex();
        $channels = self::repository(SalesChannelCollection::class, [
            function (Criteria $criteria) use ($channelId): SalesChannelCollection {
                self::assertSame([$channelId], $criteria->getIds());

                return new SalesChannelCollection([$this->salesChannel()]);
            },
        ]);
        $categories = self::repository(CategoryCollection::class, [new CategoryCollection([$this->category()])]);

        self::assertTrue(
            $this->diagnoser(
                categories: $categories,
                channels: $channels
            )->diagnose(
                self::CATEGORY_ID,
                $channelId
            )->pageFound
        );
    }

    public function testAnUnknownNamedChannelFails(): void
    {
        $channels = self::repository(SalesChannelCollection::class, [new SalesChannelCollection()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kein aktiver Verkaufskanal');

        $this->diagnoser(channels: $channels)->diagnose('Handläufe', Uuid::randomHex());
    }

    /**
     * Was: kein aktiver Kanal.
     * Warum: Ohne Kanal gibt es keine Adressen und keinen Kontext; ein leeres Ergebnis wäre irreführend.
     * Erwartet: Ausnahme mit Klartext.
     */
    public function testFailsWithoutAnyActiveChannel(): void
    {
        $channels = self::repository(
            SalesChannelCollection::class,
            [
                new SalesChannelCollection(),
                new SalesChannelCollection(),
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kein aktiver Sales-Channel gefunden.');

        $this->diagnoser(channels: $channels)->diagnose('Handläufe');
    }

    /**
     * Was: eine unbekannte Kategorie, eine ungültige Landingpage-Kennung und eine unbekannte Landingpage.
     * Warum: Der Befehl meldet „nicht gefunden“, statt mit leeren Daten weiterzurechnen.
     * Erwartet: dreimal „nicht gefunden“; für die ungültige Kennung keine Abfrage.
     */
    public function testUnknownPagesAreReportedAsNotFound(): void
    {
        $landingPages = self::repository(LandingPageCollection::class, [new LandingPageCollection()]);
        $channel = new SalesChannelCollection([$this->salesChannel()]);
        $diagnoser = $this->diagnoser(
            categories: self::repository(CategoryCollection::class, [new CategoryCollection()]),
            channels: self::repository(SalesChannelCollection::class, [$channel, $channel, $channel]),
            landingPages: $landingPages,
        );

        self::assertFalse($diagnoser->diagnose('Gibt es nicht')->pageFound);
        self::assertFalse($diagnoser->diagnoseLandingPage('keine-kennung')->pageFound);
        self::assertFalse($diagnoser->diagnoseLandingPage(self::LANDING_ID)->pageFound);
        self::assertSame([], $landingPages->searches);
    }

    /**
     * Was: Diagnose einer Landingpage.
     * Warum: Landingpages haben ihren eigenen Kontext (WebPage statt CollectionPage) und ihre Erlebniswelt am
     * Datensatz. Erwartet: Name der Landingpage und Graph im Ergebnis.
     */
    public function testALandingPageIsAnalysed(): void
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId(self::LANDING_ID);
        $landingPage->setName('Aktion');
        $landingPage->setTranslated(['name' => 'Aktion']);

        $result = $this->diagnoser(landingPages: self::repository(LandingPageCollection::class, [new LandingPageCollection([$landingPage])]))
            ->diagnoseLandingPage(self::LANDING_ID);

        self::assertTrue($result->pageFound);
        self::assertSame('Aktion', $result->pageName);
        self::assertNotNull($result->graph);
    }

    /**
     * Was: die Navigationskategorie des Kanals selbst, ohne Lieferanten, die für sie Knoten bauen.
     * Warum: Ohne Knoten bleibt der Graph leer; die Diagnose meldet das, statt abzubrechen.
     * Erwartet: Kategorie gefunden, kein Graph, keine Befunde.
     */
    public function testAPageWithoutNodesHasNoGraph(): void
    {
        $root = $this->category(self::ROOT_ID);

        $result = $this->diagnoser(categories: self::repository(CategoryCollection::class, [new CategoryCollection([$root])]), nodes: [])
            ->diagnose(self::ROOT_ID);

        self::assertTrue($result->pageFound);
        self::assertNull($result->graph);
        self::assertSame([], $result->findings);
    }

    /**
     * @param StaticEntityRepository<CategoryCollection>|null    $categories
     * @param StaticEntityRepository<CmsPageCollection>|null     $cmsPages
     * @param StaticEntityRepository<SalesChannelCollection>|null $channels
     * @param StaticEntityRepository<LandingPageCollection>|null $landingPages
     * @param list<array<string, mixed>>                         $nodes
     */
    private function diagnoser(
        ?StaticEntityRepository $categories = null,
        ?StaticEntityRepository $cmsPages = null,
        ?StaticEntityRepository $channels = null,
        ?StaticEntityRepository $landingPages = null,
        ?AbstractSalesChannelContextFactory $contextFactory = null,
        array $nodes = [['@type' => 'CollectionPage', '@id' => 'https://shop.example/#page']],
    ): SchemaDiagnoser {
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

        return new SchemaDiagnoser(
            new DiagnosisChannelResolver(
                $channels ?? self::repository(
                    SalesChannelCollection::class,
                    [
        new SalesChannelCollection(
            [
                $this->salesChannel(),
            ]
        ),
    ]
                ),
                $contextFactory ?? $this->contextFactory(),
            ),
            new DiagnosisPageLoader(
                $categories ?? self::repository(CategoryCollection::class),
                $cmsPages ?? self::repository(CmsPageCollection::class),
                $landingPages ?? self::repository(LandingPageCollection::class),
                new DocumentsSlotHydrator(
                    new CmsSlotIterator(),
                    new DocumentsCmsBlockExtractor(),
                    new DocumentsStructBuilder(new DocumentsCmsBlockExtractor()),
                    self::repository(MediaCollection::class),
                ),
            ),
            new SchemaContextFactory($seoUrlReplacer),
            new GraphBuilder([$provider]),
            new CompletenessChecker(),
            new PageWarningChecker(new CmsSlotIterator(), new YoutubeVideoExtractor()),
        );
    }

    /**
     * @return AbstractSalesChannelContextFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private function contextFactory(): AbstractSalesChannelContextFactory
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId(self::ROOT_ID);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));

        $factory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $factory->method('create')->willReturn($context);

        return $factory;
    }

    private function salesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguageId(self::LANGUAGE_ID);

        return $salesChannel;
    }

    private function category(string $id = self::CATEGORY_ID): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName('Handläufe');
        $category->setTranslated(['name' => 'Handläufe']);

        return $category;
    }
}
