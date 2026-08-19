<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert den WebSite-Singleton und verknüpft ihn per "@id" mit der Organization (publisher).
 *
 * Optional wird eine SearchAction ergänzt, sobald eine Such-URL-Vorlage konfiguriert ist
 * (Sitelinks-Searchbox).
 */
final class WebSiteNodeProvider implements SchemaNodeProvider
{
    private const CONFIG_PREFIX = 'RcStructuredData.config.';
    private const SHOP_NAME_KEY = 'core.basicInformation.shopName';
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
        $configured = $this->getString($context, 'websiteName');
        if ($configured !== '') {
            return $configured;
        }

        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->systemConfig->getString(self::SHOP_NAME_KEY, $salesChannelId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSearchAction(SchemaContext $context): ?array
    {
        $template = $this->getString($context, 'websiteSearchUrl');
        if ($template === '' || !str_contains($template, self::SEARCH_TERM_PLACEHOLDER)) {
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

    private function getString(SchemaContext $context, string $key): string
    {
        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->systemConfig->getString(self::CONFIG_PREFIX . $key, $salesChannelId));
    }
}
