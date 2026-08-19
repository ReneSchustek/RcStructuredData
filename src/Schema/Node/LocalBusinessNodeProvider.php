<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert einen LocalBusiness-Singleton aus der Plugin-Konfiguration (lokale SEO).
 *
 * Nur aktiv, wenn eine Adresse (Straße + Ort) gepflegt ist — sonst hätte der Knoten für Google keinen
 * Wert. Ergänzt Adresse, Telefon, Geo-Koordinaten, Preisspanne und Öffnungszeiten, sofern konfiguriert.
 */
final class LocalBusinessNodeProvider implements SchemaNodeProvider
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
        return $this->getString($context, 'localBusinessStreet') !== ''
            && $this->getString($context, 'localBusinessCity') !== '';
    }

    public function provide(SchemaContext $context): array
    {
        $address = ['@type' => 'PostalAddress'];
        $this->addIfSet($address, 'streetAddress', $this->getString($context, 'localBusinessStreet'));
        $this->addIfSet($address, 'postalCode', $this->getString($context, 'localBusinessZip'));
        $this->addIfSet($address, 'addressLocality', $this->getString($context, 'localBusinessCity'));
        $this->addIfSet($address, 'addressCountry', $this->getString($context, 'localBusinessCountry'));

        $node = [
            '@type' => 'LocalBusiness',
            '@id' => $this->idFactory->localBusiness($context),
            'name' => $this->resolveName($context),
            'url' => $context->getBaseUrl(),
            'address' => $address,
        ];

        $this->addIfSet($node, 'telephone', $this->getString($context, 'localBusinessPhone'));
        $this->addIfSet($node, 'priceRange', $this->getString($context, 'localBusinessPriceRange'));

        $geo = $this->resolveGeo($context);
        if ($geo !== null) {
            $node['geo'] = $geo;
        }

        $openingHours = $this->resolveOpeningHours($context);
        if ($openingHours !== []) {
            $node['openingHours'] = $openingHours;
        }

        return [$node];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveGeo(SchemaContext $context): ?array
    {
        $latitude = $this->getString($context, 'localBusinessLatitude');
        $longitude = $this->getString($context, 'localBusinessLongitude');
        if ($latitude === '' || $longitude === '') {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveOpeningHours(SchemaContext $context): array
    {
        $raw = $this->getString($context, 'localBusinessOpeningHours');
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    private function resolveName(SchemaContext $context): string
    {
        $configured = $this->getString($context, 'organizationName');
        if ($configured !== '') {
            return $configured;
        }

        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->systemConfig->getString(self::SHOP_NAME_KEY, $salesChannelId));
    }

    /**
     * @param array<string, mixed> $target
     */
    private function addIfSet(array &$target, string $key, string $value): void
    {
        if ($value !== '') {
            $target[$key] = $value;
        }
    }

    private function getString(SchemaContext $context, string $key): string
    {
        $salesChannelId = $context->getSalesChannelContext()->getSalesChannelId();

        return trim($this->systemConfig->getString(self::CONFIG_PREFIX . $key, $salesChannelId));
    }
}
