<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Subscriber;

use Ruhrcoder\RcStructuredData\Schema\Diagnostics\CompletenessChecker;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaContextFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaGraphStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hängt den fertigen JSON-LD-Graphen als Page-Extension an Kategorie- und Landingpages.
 *
 * Enthält keine Geschäftslogik: Kontext-Aufbau (Factory) und Graph-Bau (GraphBuilder) sind
 * delegiert; der Subscriber verdrahtet nur Event und Page-Extension.
 */
final class SchemaGraphSubscriber implements EventSubscriberInterface
{
    private const DEBUG_CONFIG_KEY = 'RcStructuredData.config.debug';

    public function __construct(
        private readonly SchemaContextFactory $contextFactory,
        private readonly GraphBuilder $graphBuilder,
        private readonly CompletenessChecker $completenessChecker,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            LandingPageLoadedEvent::class => 'onLandingPageLoaded',
        ];
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $this->apply(
            $event->getPage(),
            $this->contextFactory->fromNavigationPage($event->getPage(), $event->getSalesChannelContext()),
            $event->getSalesChannelContext()->getSalesChannelId(),
        );
    }

    public function onLandingPageLoaded(LandingPageLoadedEvent $event): void
    {
        $this->apply(
            $event->getPage(),
            $this->contextFactory->fromLandingPage($event->getPage(), $event->getSalesChannelContext()),
            $event->getSalesChannelContext()->getSalesChannelId(),
        );
    }

    private function apply(Page $page, ?SchemaContext $context, string $salesChannelId): void
    {
        if ($context === null) {
            return;
        }

        $graph = $this->graphBuilder->build($context);
        if ($graph === null) {
            return;
        }

        $page->addExtension(
            SchemaGraphStruct::EXTENSION_NAME,
            new SchemaGraphStruct($graph, $this->resolveDiagnostics($graph, $salesChannelId)),
        );
    }

    /**
     * Vollständigkeits-Hinweise nur bei aktivem Debug-Schalter — der Produktiv-Output bleibt sauber.
     *
     * @param array<string, mixed> $graph
     *
     * @return list<string>
     */
    private function resolveDiagnostics(array $graph, string $salesChannelId): array
    {
        if (!$this->systemConfig->getBool(self::DEBUG_CONFIG_KEY, $salesChannelId)) {
            return [];
        }

        return $this->completenessChecker->describe($graph);
    }
}
