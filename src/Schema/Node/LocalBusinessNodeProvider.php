<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\PluginConfigReader;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Liefert einen LocalBusiness-Singleton aus der Plugin-Konfiguration (lokale SEO).
 *
 * Nur aktiv, wenn eine Adresse (Straße + Ort) gepflegt ist, sonst hätte der Knoten für Google keinen
 * Wert. Ergänzt Adresse, Telefon, Bild, Geo-Koordinaten, Preisspanne und Öffnungszeiten, sofern konfiguriert.
 *
 * Typ `HomeAndConstructionBusiness`: ein Untertyp von LocalBusiness für Baubedarf und Handwerk; Google wertet ihn wie
 * LocalBusiness, er beschreibt den Betrieb aber genauer. Bild: eigene Einstellung, sonst das Logo der Organisation;
 * Google empfiehlt `image` für Unternehmensangaben. Öffnungszeiten als `openingHoursSpecification` (empfohlen statt
 * des Textes `openingHours`); eine Zeile, die sich nicht lesen lässt, bleibt als Text erhalten.
 */
final class LocalBusinessNodeProvider implements SchemaNodeProvider
{
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

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly IdFactory $idFactory,
    ) {
    }

    public function supports(SchemaContext $context): bool
    {
        return $this->config()->string('localBusinessStreet', $context->getSalesChannelContext()->getSalesChannelId()) !== ''
            && $this->config()->string('localBusinessCity', $context->getSalesChannelContext()->getSalesChannelId()) !== '';
    }

    public function provide(SchemaContext $context): array
    {
        $address = $this->buildAddress($context);
        $node = $this->buildBaseNode($context, $address);
        $this->addOptionalFields($node, $context);
        $this->addGeoAndOpeningHours($node, $context);

        return [$node];
    }

    /**
     * Die Anschrift aus den Einstellungen; ein leeres Feld fehlt.
     *
     * @return array<string, string>
     */
    private function buildAddress(SchemaContext $context): array
    {
        $address = ['@type' => 'PostalAddress'];
        $this->addIfSet($address, 'streetAddress', $this->config()->string('localBusinessStreet', $context->getSalesChannelContext()->getSalesChannelId()));
        $this->addIfSet($address, 'postalCode', $this->config()->string('localBusinessZip', $context->getSalesChannelContext()->getSalesChannelId()));
        $this->addIfSet($address, 'addressLocality', $this->config()->string('localBusinessCity', $context->getSalesChannelContext()->getSalesChannelId()));
        $this->addIfSet($address, 'addressCountry', $this->config()->string('localBusinessCountry', $context->getSalesChannelContext()->getSalesChannelId()));

        return $address;
    }

    /**
     * @param array<string, string> $address
     *
     * @return array<string, mixed>
     */
    private function buildBaseNode(SchemaContext $context, array $address): array
    {
        return [
            '@type' => self::TYPE,
            '@id' => $this->idFactory->localBusiness($context),
            'name' => $this->config()->organizationName($context->getSalesChannelContext()->getSalesChannelId()),
            'url' => $context->getBaseUrl(),
            'address' => $address,
        ];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function addOptionalFields(array &$node, SchemaContext $context): void
    {
        $this->addIfSet($node, 'image', $this->resolveImage($context));
        $this->addIfSet($node, 'telephone', $this->config()->string('localBusinessPhone', $context->getSalesChannelContext()->getSalesChannelId()));
        $this->addIfSet($node, 'priceRange', $this->config()->string('localBusinessPriceRange', $context->getSalesChannelContext()->getSalesChannelId()));
    }

    /**
     * @param array<string, mixed> $node
     */
    private function addGeoAndOpeningHours(array &$node, SchemaContext $context): void
    {
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
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveGeo(SchemaContext $context): ?array
    {
        $latitude = $this->config()->string('localBusinessLatitude', $context->getSalesChannelContext()->getSalesChannelId());
        $longitude = $this->config()->string('localBusinessLongitude', $context->getSalesChannelContext()->getSalesChannelId());
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
        $raw = $this->config()->string('localBusinessOpeningHours', $context->getSalesChannelContext()->getSalesChannelId());
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    private function resolveImage(SchemaContext $context): string
    {
        $image = $this->config()->string('localBusinessImage', $context->getSalesChannelContext()->getSalesChannelId());

        return $image !== '' ? $image : $this->config()->string('organizationLogo', $context->getSalesChannelContext()->getSalesChannelId());
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
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z]{2}(?:-[A-Za-z]{2})?(?:\s*,\s*[A-Za-z]{2}(?:-[A-Za-z]{2})?)*)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $line, $match) !== 1) {
                $unreadable[] = $line;
                continue;
            }

            $days = self::daysOf($match[1]);
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

    /**
     * Die Wochentage zu „Mo-Th" oder „Mo,We" als schema.org-Adressen; leer, wenn ein Teil nicht lesbar ist.
     *
     * @return list<string>
     */
    private static function daysOf(string $dayPart): array
    {
        $dayKeys = array_keys(self::DAYS);
        $days = [];
        foreach (preg_split('/\s*,\s*/', $dayPart) ?: [] as $part) {
            $range = explode('-', $part);
            $from = array_search(ucfirst(strtolower($range[0])), $dayKeys, true);
            $to = array_search(ucfirst(strtolower($range[1] ?? $range[0])), $dayKeys, true);
            if ($from === false || $to === false || $to < $from) {
                return [];
            }
            for ($day = $from; $day <= $to; ++$day) {
                $days[] = 'https://schema.org/' . self::DAYS[$dayKeys[$day]];
            }
        }

        return $days;
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

    private function config(): PluginConfigReader
    {
        return new PluginConfigReader($this->systemConfig);
    }
}
