<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsSlotHydrator;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Lädt Kategorie, Landingpage und Erlebniswelt für die Diagnose.
 *
 * Die Slot-Konfiguration kommt ohne Storefront-DataResolver; ein gepflegtes previewMedia-Thumbnail wird daher nicht
 * aufgelöst (Rückfall auf das YouTube-Standardbild), was für die Feld-Vollständigkeit ohne Belang ist. Nachweise
 * (rc-documents) werden dagegen aufgelöst, denn ohne Datei gäbe es keinen Knoten.
 */
final class DiagnosisPageLoader
{
    /**
     * @param EntityRepository<CategoryCollection>    $categoryRepository
     * @param EntityRepository<CmsPageCollection>     $cmsPageRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $landingPageRepository,
        private readonly DocumentsSlotHydrator $documentsSlotHydrator,
    ) {
    }

    /**
     * Eine Kategorie über Kennung oder Namen.
     */
    public function category(string $identifier, SalesChannelContext $salesChannelContext): ?CategoryEntity
    {
        // Eine Kennung gehört in den Criteria-Aufbau, nicht in einen Filter: Shopware löst sie dann über den
        // Primärschlüssel auf, statt eine Bedingung über die Tabelle zu legen.
        $criteria = Uuid::isValid($identifier) ? new Criteria([$identifier]) : new Criteria();
        if (!Uuid::isValid($identifier)) {
            $criteria->addFilter(new EqualsFilter('name', $identifier));
        }
        $criteria->setLimit(1);

        $category = $this->categoryRepository->search(
            $criteria,
            $salesChannelContext->getContext()
        )->getEntities()->first();

        return $category instanceof CategoryEntity ? $category : null;
    }

    /**
     * Die Erlebniswelt der Kategorie samt aufgelösten Nachweisen.
     */
    public function cmsPageOf(CategoryEntity $category, SalesChannelContext $salesChannelContext): ?CmsPageEntity
    {
        $cmsPageId = $category->getCmsPageId();
        $cmsPage = null;
        if ($cmsPageId !== null) {
            $criteria = new Criteria([$cmsPageId]);
            $criteria->addAssociation('sections.blocks.slots');
            $found = $this->cmsPageRepository->search(
                $criteria,
                $salesChannelContext->getContext()
            )->getEntities()->first();
            $cmsPage = $found instanceof CmsPageEntity ? $found : null;
        }

        $this->documentsSlotHydrator->hydrate($cmsPage, $salesChannelContext->getContext());

        return $cmsPage;
    }

    /**
     * Eine Landingpage samt Erlebniswelt und aufgelösten Nachweisen.
     */
    public function landingPage(string $landingPageId, SalesChannelContext $salesChannelContext): ?LandingPageEntity
    {
        if (!Uuid::isValid($landingPageId)) {
            return null;
        }

        $criteria = new Criteria([$landingPageId]);
        $criteria->addAssociation('cmsPage.sections.blocks.slots');

        $landingPage = $this->landingPageRepository->search(
            $criteria,
            $salesChannelContext->getContext()
        )->getEntities()->first();
        if (!$landingPage instanceof LandingPageEntity) {
            return null;
        }

        $this->documentsSlotHydrator->hydrate($landingPage->getCmsPage(), $salesChannelContext->getContext());

        return $landingPage;
    }
}
