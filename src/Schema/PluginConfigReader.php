<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liest die Einstellungen dieses Plugins je Verkaufskanal: getrimmt, als Zeilenliste, und den Namen der Organisation
 * mit Rückfall auf den Shop-Namen. Organisation, Geschäft und Website lasen das vorher jede für sich.
 */
final readonly class PluginConfigReader
{
    private const CONFIG_PREFIX = 'RcStructuredData.config.';
    private const SHOP_NAME_KEY = 'core.basicInformation.shopName';

    public function __construct(
        private SystemConfigService $systemConfig,
    ) {
    }

    public function string(string $key, ?string $salesChannelId): string
    {
        return trim($this->systemConfig->getString(self::CONFIG_PREFIX . $key, $salesChannelId));
    }

    /**
     * Eine Einstellung mit einem Wert je Zeile, ohne leere Zeilen.
     *
     * @return list<string>
     */
    public function lines(string $key, ?string $salesChannelId): array
    {
        $raw = $this->string($key, $salesChannelId);
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Der Name der Organisation; ist keiner eingestellt, der Shop-Name. So hat der Knoten auch in einem Shop ohne
     * Zusatzpflege einen Namen.
     */
    public function organizationName(?string $salesChannelId): string
    {
        $configured = $this->string('organizationName', $salesChannelId);

        return $configured !== '' ? $configured : $this->shopName($salesChannelId);
    }

    public function shopName(?string $salesChannelId): string
    {
        return trim($this->systemConfig->getString(self::SHOP_NAME_KEY, $salesChannelId));
    }
}
