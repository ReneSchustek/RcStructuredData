<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use RuntimeException;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Fährt den Graph-Builder für eine einzelne Kategorie und ermittelt die Vollständigkeits-Lücken.
 *
 * Kapselt die für die Diagnose nötige DAL-Beschaffung (Sales-Channel-Kontext, Kategorie, CMS-Seite),
 * damit der Konsolenbefehl reine Ein-/Ausgabe bleibt (SoC). Die Slot-Konfiguration wird ohne
 * Storefront-DataResolver geladen; ein gepflegtes previewMedia-Thumbnail wird daher nicht aufgelöst
 * (Fallback auf das YouTube-Standardbild), was für die Feld-Vollständigkeit ohne Belang ist.
 */
final class SchemaDiagnoser implements CategoryDiagnoser
{
    /**
     * @param EntityRepository<CategoryCollection>     $categoryRepository
     * @param EntityRepository<CmsPageCollection>      $cmsPageRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<LandingPageCollection>  $landingPageRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly SchemaContextFactory $schemaContextFactory,
        private readonly GraphBuilder $graphBuilder,
        private readonly CompletenessChecker $completenessChecker,
        private readonly EntityRepository $landingPageRepository,
        private readonly PageWarningChecker $pageWarningChecker,
    ) {
    }

    public function diagnose(string $identifier): DiagnoseResult
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $category = $this->findCategory($identifier, $salesChannelContext);
        if ($category === null) {
            return DiagnoseResult::categoryNotFound();
        }

        $cmsPage = $this->loadCmsPage($category, $salesChannelContext);
        $schemaContext = $this->schemaContextFactory->fromCategory($category, $cmsPage, $salesChannelContext);
        if ($schemaContext === null) {
            return DiagnoseResult::analysed(
                (string) $category->getTranslation('name'),
                null,
                [],
                $this->pageWarningChecker->check(null, $cmsPage),
            );
        }

        $graph = $this->graphBuilder->build($schemaContext);
        $findings = $graph !== null ? $this->completenessChecker->check($graph) : [];

        return DiagnoseResult::analysed(
            (string) $category->getTranslation('name'),
            $graph,
            $findings,
            $this->pageWarningChecker->check($graph, $cmsPage),
        );
    }

    public function diagnoseLandingPage(string $landingPageId): DiagnoseResult
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $landingPage = $this->findLandingPage($landingPageId, $salesChannelContext);
        if ($landingPage === null) {
            return DiagnoseResult::categoryNotFound();
        }

        $schemaContext = $this->schemaContextFactory->fromLandingPageEntity($landingPage, $salesChannelContext);
        $graph = $this->graphBuilder->build($schemaContext);
        $findings = $graph !== null ? $this->completenessChecker->check($graph) : [];

        return DiagnoseResult::analysed(
            (string) $landingPage->getTranslation('name'),
            $graph,
            $findings,
            $this->pageWarningChecker->check($graph, $landingPage->getCmsPage()),
        );
    }

    private function findLandingPage(string $landingPageId, SalesChannelContext $salesChannelContext): ?LandingPageEntity
    {
        if (!Uuid::isValid($landingPageId)) {
            return null;
        }

        $criteria = new Criteria([$landingPageId]);
        $criteria->addAssociation('cmsPage.sections.blocks.slots');

        $landingPage = $this->landingPageRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();

        return $landingPage instanceof LandingPageEntity ? $landingPage : null;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            // Fallback: irgendein aktiver Sales-Channel, falls kein Storefront-Typ existiert.
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->addFilter(new EqualsFilter('active', true));
            $fallbackCriteria->setLimit(1);
            $salesChannel = $this->salesChannelRepository->search($fallbackCriteria, $context)->getEntities()->first();
        }

        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new RuntimeException('Kein aktiver Sales-Channel gefunden.');
        }

        // Die Default-Sprache der Sales-Channel ist garantiert in ihrer Sprachliste — anders als
        // die System-Sprache, die der Context-Factory-Default sonst annimmt.
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannel->getId(),
            [SalesChannelContextService::LANGUAGE_ID => $salesChannel->getLanguageId()],
        );
    }

    private function findCategory(string $identifier, SalesChannelContext $salesChannelContext): ?CategoryEntity
    {
        // Eine Kennung gehört in den Criteria-Aufbau, nicht in einen Filter: Shopware löst sie
        // dann über den Primärschlüssel auf, statt eine Bedingung über die Tabelle zu legen.
        $criteria = Uuid::isValid($identifier) ? new Criteria([$identifier]) : new Criteria();
        if (!Uuid::isValid($identifier)) {
            $criteria->addFilter(new EqualsFilter('name', $identifier));
        }
        $criteria->setLimit(1);

        $category = $this->categoryRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();

        return $category instanceof CategoryEntity ? $category : null;
    }

    private function loadCmsPage(CategoryEntity $category, SalesChannelContext $salesChannelContext): ?CmsPageEntity
    {
        $cmsPageId = $category->getCmsPageId();
        if ($cmsPageId === null) {
            return null;
        }

        $criteria = new Criteria([$cmsPageId]);
        $criteria->addAssociation('sections.blocks.slots');

        $cmsPage = $this->cmsPageRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();

        return $cmsPage instanceof CmsPageEntity ? $cmsPage : null;
    }
}
