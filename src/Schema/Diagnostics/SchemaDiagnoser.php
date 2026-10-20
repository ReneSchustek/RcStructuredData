<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Shopware\Core\Content\Cms\CmsPageEntity;

/**
 * Fährt den Graph-Builder für eine einzelne Kategorie oder Landingpage und ermittelt die Vollständigkeits-Lücken.
 *
 * Kanal und Seiten beschaffen {@see DiagnosisChannelResolver} und {@see DiagnosisPageLoader}; der Konsolenbefehl und
 * der Endpunkt bleiben reine Ein- und Ausgabe.
 */
final class SchemaDiagnoser implements CategoryDiagnoser
{
    public function __construct(
        private readonly DiagnosisChannelResolver $channelResolver,
        private readonly DiagnosisPageLoader $pageLoader,
        private readonly SchemaContextFactory $schemaContextFactory,
        private readonly GraphBuilder $graphBuilder,
        private readonly CompletenessChecker $completenessChecker,
        private readonly PageWarningChecker $pageWarningChecker,
    ) {
    }

    public function diagnose(string $identifier, ?string $salesChannelId = null): DiagnoseResult
    {
        $salesChannelContext = $this->channelResolver->contextFor($salesChannelId);

        $category = $this->pageLoader->category($identifier, $salesChannelContext);
        if ($category === null) {
            return DiagnoseResult::notFound();
        }

        $cmsPage = $this->pageLoader->cmsPageOf($category, $salesChannelContext);

        return $this->analyse(
            (string) $category->getTranslation('name'),
            $this->schemaContextFactory->fromCategory($category, $cmsPage, $salesChannelContext),
            $cmsPage,
        );
    }

    public function diagnoseLandingPage(string $landingPageId, ?string $salesChannelId = null): DiagnoseResult
    {
        $salesChannelContext = $this->channelResolver->contextFor($salesChannelId);

        $landingPage = $this->pageLoader->landingPage($landingPageId, $salesChannelContext);
        if ($landingPage === null) {
            return DiagnoseResult::notFound();
        }

        return $this->analyse(
            (string) $landingPage->getTranslation('name'),
            $this->schemaContextFactory->fromLandingPageEntity($landingPage, $salesChannelContext),
            $landingPage->getCmsPage(),
        );
    }

    /**
     * Graph, Lücken und Seitenwarnungen einer gefundenen Seite. Ohne Kontext oder ohne Knoten gibt es keinen Graphen;
     * die Warnungen zur Seite selbst gelten trotzdem.
     */
    private function analyse(string $name, ?SchemaContext $schemaContext, ?CmsPageEntity $cmsPage): DiagnoseResult
    {
        $graph = $schemaContext !== null ? $this->graphBuilder->build($schemaContext) : null;

        return DiagnoseResult::analysed(
            $name,
            $graph,
            $graph !== null ? $this->completenessChecker->check($graph) : [],
            $this->pageWarningChecker->check($graph, $cmsPage),
        );
    }
}
