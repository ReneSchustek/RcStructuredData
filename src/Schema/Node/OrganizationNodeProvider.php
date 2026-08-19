<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert den Organization-Singleton aus der Plugin-Konfiguration.
 *
 * Ist kein Name konfiguriert, wird auf den Shop-Namen zurückgegriffen, damit der Knoten
 * (und die daran hängenden Referenzen wie publisher) auch in einem normal eingerichteten Shop
 * ohne Zusatzkonfiguration funktioniert.
 */
final class OrganizationNodeProvider implements SchemaNodeProvider
{
    private const CONFIG_PREFIX = 'RcStructuredData.config.';
    private const SHOP_NAME_KEY = 'core.basicInformation.shopName';

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
            '@type' => 'Organization',
            '@id' => $this->idFactory->organization($context),
            'name' => $this->resolveName($context),
            'url' => $context->getBaseUrl(),
        ];

        $logo = $this->getString($context, 'organizationLogo');
        if ($logo !== '') {
            $node['logo'] = $logo;
        }

        $sameAs = $this->resolveSameAs($context);
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return [$node];
    }

    private function resolveName(SchemaContext $context): string
    {
        $configured = $this->getString($context, 'organizationName');
        if ($configured !== '') {
            return $configured;
        }

        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();
        $shopName = $this->systemConfig->getString(self::SHOP_NAME_KEY, $salesChannelId);

        return trim($shopName);
    }

    /**
     * @return list<string>
     */
    private function resolveSameAs(SchemaContext $context): array
    {
        $raw = $this->getString($context, 'organizationSameAs');
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    private function getString(SchemaContext $context, string $key): string
    {
        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->systemConfig->getString(self::CONFIG_PREFIX . $key, $salesChannelId));
    }
}
