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
 * Wert. Ergänzt Adresse, Telefon, Bild, Geo-Koordinaten, Preisspanne und Öffnungszeiten, sofern konfiguriert.
 *
 * **Typ `HomeAndConstructionBusiness`**: ein Untertyp von LocalBusiness für Baubedarf und Handwerk; Google wertet ihn wie
 * LocalBusiness, er beschreibt den Betrieb aber genauer. **Bild**: eigene Einstellung, sonst das Logo der Organisation;
 * Google empfiehlt `image` für Unternehmensangaben. **Öffnungszeiten** als `openingHoursSpecification` (empfohlen statt
 * des Textes `openingHours`); eine Zeile, die sich nicht lesen lässt, bleibt als Text erhalten.
 */
final class LocalBusinessNodeProvider implements SchemaNodeProvider
{
    private const CONFIG_PREFIX = 'RcStructuredData.config.';
    private const TYPE = 'HomeAndConstructionBusiness';
    private const DAYS = [
        'Mo' => 'Monday',
        'Tu' => 'Tuesday',
        'We' => 'Wednesday',
        'Th' => 'Thursday',
        'Fr' => 'Friday',
        'Sa' => 'Saturday',
        'Su' => 'Sunday',
    ];
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
            '@type' => self::TYPE,
            '@id' => $this->idFactory->localBusiness($context),
            'name' => $this->resolveName($context),
            'url' => $context->getBaseUrl(),
            'address' => $address,
        ];

        $this->addIfSet($node, 'image', $this->resolveImage($context));
        $this->addIfSet($node, 'telephone', $this->getString($context, 'localBusinessPhone'));
        $this->addIfSet($node, 'priceRange', $this->getString($context, 'localBusinessPriceRange'));

        $geo = $this->resolveGeo($context);
        if ($geo !== null) {
            $node['geo'] = $geo;
        }

        [$specification, $unreadable] = self::openingHoursSpecification($this->resolveOpeningHours($context));
        if ($specification !== []) {
            $node['openingHoursSpecification'] = $specification;
        }
        if ($unreadable !== []) {
            $node['openingHours'] = $unreadable;
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

    private function resolveImage(SchemaContext $context): string
    {
        $image = $this->getString($context, 'localBusinessImage');

        return $image !== '' ? $image : $this->getString($context, 'organizationLogo');
    }

    /**
     * Zeilen wie „Mo-Th 08:00-17:00" oder „Mo,We 08:00-12:00" als `OpeningHoursSpecification`.
     *
     * @param list<string> $lines
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>} Spezifikationen und nicht lesbare Zeilen
     */
    public static function openingHoursSpecification(array $lines): array
    {
        $specifications = [];
        $unreadable = [];
        $dayKeys = array_keys(self::DAYS);

        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z]{2}(?:-[A-Za-z]{2})?(?:\s*,\s*[A-Za-z]{2}(?:-[A-Za-z]{2})?)*)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $line, $match) !== 1) {
                $unreadable[] = $line;
                continue;
            }

            $days = [];
            foreach (preg_split('/\s*,\s*/', $match[1]) ?: [] as $part) {
                $range = explode('-', $part);
                $from = array_search(ucfirst(strtolower($range[0])), $dayKeys, true);
                $to = array_search(ucfirst(strtolower($range[1] ?? $range[0])), $dayKeys, true);
                if ($from === false || $to === false || $to < $from) {
                    $days = [];
                    break;
                }
                for ($day = $from; $day <= $to; ++$day) {
                    $days[] = 'https://schema.org/' . self::DAYS[$dayKeys[$day]];
                }
            }

            if ($days === []) {
                $unreadable[] = $line;
                continue;
            }

            $specifications[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days,
                'opens' => str_pad($match[2], 5, '0', \STR_PAD_LEFT),
                'closes' => str_pad($match[3], 5, '0', \STR_PAD_LEFT),
            ];
        }

        return [$specifications, $unreadable];
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
