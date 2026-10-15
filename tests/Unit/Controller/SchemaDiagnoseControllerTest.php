<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsSlotHydrator;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsStructBuilder;
use Ruhrcoder\RcStructuredData\Controller\SchemaDiagnoseController;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CompletenessChecker;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\PageWarningChecker;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\SchemaDiagnoser;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Prüft die Antwort der Diagnose an die Verwaltung: dieselben Felder für Kategorie und Landingpage.
 */
#[CoversClass(SchemaDiagnoseController::class)]
final class SchemaDiagnoseControllerTest extends TestCase
{
    use StaticRepositoryTrait;

    private const CATEGORY_ID = '0190aa000000000000000000000000c1';

    /**
     * Was: eine Kategorie, für die kein Lieferant Knoten baut.
     * Warum: Die Verwaltung zeigt „gefunden, aber ohne Graph“ anders an als „nicht gefunden“.
     * Erwartet: gefunden, mit Name, ohne Graph, ohne Knoten, Befunde und Warnungen.
     */
    public function testACategoryAnswersWithAllFields(): void
    {
        $category = new CategoryEntity();
        $category->setId(self::CATEGORY_ID);
        $category->setName('Handläufe');
        $category->setTranslated(['name' => 'Handläufe']);

        $response = $this->controller(self::repository(CategoryCollection::class, [new CategoryCollection([$category])]))
            ->diagnoseCategory(self::CATEGORY_ID);

        self::assertSame(
            ['found' => true, 'name' => 'Handläufe', 'hasGraph' => false, 'nodeCount' => 0, 'findings' => [], 'warnings' => []],
            json_decode((string) $response->getContent(), true),
        );
    }

    /**
     * Was: eine ungültige Kennung einer Landingpage.
     * Warum: Die Verwaltung braucht eine Antwort, keinen Fehler.
     * Erwartet: nicht gefunden.
     */
    public function testAnUnknownLandingPageIsNotFound(): void
    {
        $response = $this->controller(self::repository(CategoryCollection::class))->diagnoseLandingPage('keine-kennung');

        $content = json_decode((string) $response->getContent(), true);
        self::assertIsArray($content);
        self::assertFalse($content['found']);
    }

    /**
     * @param StaticEntityRepository<CategoryCollection> $categories
     */
    private function controller(StaticEntityRepository $categories): SchemaDiagnoseController
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('0190aa000000000000000000000000b1');
        $salesChannel->setLanguageId('0190aa000000000000000000000000a9');
        $salesChannel->setNavigationCategoryId('0190aa000000000000000000000000c0');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));
        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($context);

        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn('https://shop.example/');

        return new SchemaDiagnoseController(new SchemaDiagnoser(
            $categories,
            self::repository(CmsPageCollection::class),
            self::repository(SalesChannelCollection::class, [new SalesChannelCollection([$salesChannel])]),
            $contextFactory,
            new SchemaContextFactory($seoUrlReplacer),
            new GraphBuilder([]),
            new CompletenessChecker(),
            self::repository(LandingPageCollection::class),
            new PageWarningChecker(new CmsSlotIterator(), new YoutubeVideoExtractor()),
            new DocumentsSlotHydrator(
                new CmsSlotIterator(),
                new DocumentsCmsBlockExtractor(),
                new DocumentsStructBuilder(new DocumentsCmsBlockExtractor()),
                self::repository(MediaCollection::class),
            ),
        ));
    }
}
