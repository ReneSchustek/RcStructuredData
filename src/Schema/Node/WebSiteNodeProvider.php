<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\PluginConfigReader;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert den WebSite-Singleton und verknüpft ihn per "@id" mit der Organization (publisher).
 *
 * Optional wird eine SearchAction ergänzt, sobald eine Such-URL-Vorlage konfiguriert ist. Ein Suchfeld zeigt Google
 * dafür seit November 2024 nicht mehr an; die Angabe beschreibt nur noch die Suche des Shops.
 */
final class WebSiteNodeProvider implements SchemaNodeProvider
{
    private const SEARCH_TERM_PLACEHOLDER = '{search_term_string}';

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $this->resolveName($context) !== '';
    }

    public function provide(SchemaContext $context): array
    {
        $node = [
            '@type' => 'WebSite',
            '@id' => $this->idFactory->website($context),
            'url' => $context->getBaseUrl(),
            'name' => $this->resolveName($context),
            'inLanguage' => $context->getLocaleCode(),
            'publisher' => ['@id' => $this->idFactory->organization($context)],
        ];

        $searchAction = $this->resolveSearchAction($context);
        if ($searchAction !== null) {
            $node['potentialAction'] = $searchAction;
        }

        return [$node];
    }

    private function resolveName(SchemaContext $context): string
    {
        $configured = $this->config()->string('websiteName', $context->getSalesChannelContext()->getSalesChannelId());
        if ($configured !== '') {
            return $configured;
        }

        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->config()->shopName($salesChannelId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSearchAction(SchemaContext $context): ?array
    {
        $template = $this->config()->string(
            'websiteSearchUrl',
            $context->getSalesChannelContext()->getSalesChannelId()
        );
        if (!str_starts_with($template, 'https://') || !str_contains($template, self::SEARCH_TERM_PLACEHOLDER)) {
            return null;
        }

        return [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $template,
            ],
            'query-input' => 'required name=search_term_string',
        ];
    }

    private function config(): PluginConfigReader
    {
        return new PluginConfigReader($this->systemConfig);
    }
}
