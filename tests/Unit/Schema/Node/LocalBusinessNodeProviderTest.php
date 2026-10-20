<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\LocalBusinessNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Prüft das Geschäft vor Ort: Ohne Straße und Ort entsteht kein Knoten, das Bild fällt auf das Logo zurück, unlesbare
 * Öffnungszeiten und Koordinaten bleiben weg.
 */
class LocalBusinessNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    private const PREFIX = 'RcStructuredData.config.';

    public function testNotSupportedWithoutAddress(): void
    {
        $provider = new LocalBusinessNodeProvider($this->systemConfig([]), new IdFactory());

        static::assertFalse($provider->supports($this->makeContext()));
    }

    public function testNotSupportedWithStreetButNoCity(): void
    {
        $provider = new LocalBusinessNodeProvider(
            $this->systemConfig([self::PREFIX . 'localBusinessStreet' => 'Musterstr. 1']),
            new IdFactory(),
        );

        static::assertFalse($provider->supports($this->makeContext()));
    }

    public function testBuildsFullLocalBusinessNode(): void
    {
        $provider = new LocalBusinessNodeProvider(
            $this->systemConfig([
                self::PREFIX . 'organizationName' => 'Muster Edelstahl GmbH',
                self::PREFIX . 'localBusinessStreet' => 'Musterstr. 1',
                self::PREFIX . 'localBusinessZip' => '44787',
                self::PREFIX . 'localBusinessCity' => 'Bochum',
                self::PREFIX . 'localBusinessCountry' => 'DE',
                self::PREFIX . 'localBusinessPhone' => '+49 234 123456',
                self::PREFIX . 'localBusinessPriceRange' => '€€',
                self::PREFIX . 'localBusinessLatitude' => '51.48',
                self::PREFIX . 'localBusinessLongitude' => '7.22',
                self::PREFIX . 'localBusinessOpeningHours' => "Mo-Fr 09:00-18:00\nSa 10:00-14:00",
            ]),
            new IdFactory(),
        );
        $context = $this->makeContext(baseUrl: 'https://shop.example/');

        static::assertTrue($provider->supports($context));

        $node = $provider->provide($context)[0];
        static::assertSame('HomeAndConstructionBusiness', $node['@type']);
        static::assertSame('https://shop.example/#localbusiness', $node['@id']);
        static::assertSame('Muster Edelstahl GmbH', $node['name']);
        static::assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Musterstr. 1',
            'postalCode' => '44787',
            'addressLocality' => 'Bochum',
            'addressCountry' => 'DE',
        ], $node['address']);
        static::assertSame('+49 234 123456', $node['telephone']);
        static::assertSame('€€', $node['priceRange']);
        static::assertSame(['@type' => 'GeoCoordinates', 'latitude' => '51.48', 'longitude' => '7.22'], $node['geo']);
        static::assertSame([
            [
                '@type' => 'OpeningHoursSpecification',
'dayOfWeek' => [
    'https://schema.org/Monday',
    'https://schema.org/Tuesday',
    'https://schema.org/Wednesday',
    'https://schema.org/Thursday',
    'https://schema.org/Friday',
],
                'opens' => '09:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['https://schema.org/Saturday'],
                'opens' => '10:00',
                'closes' => '14:00',
            ],
        ], $node['openingHoursSpecification']);
        static::assertArrayNotHasKey('openingHours', $node);
    }

    /**
     * Was: Bild des Geschäfts.
     * Warum: Google empfiehlt `image`; ohne eigenes Foto ist das Logo besser als nichts.
     * Erwartet: eigenes Bild vor Logo, Logo als Rückfall.
     */
    public function testImageFallsBackToTheLogo(): void
    {
        $base = [
            self::PREFIX . 'localBusinessStreet' => 'Musterstr. 1',
            self::PREFIX . 'localBusinessCity' => 'Bochum',
            self::PREFIX . 'organizationLogo' => 'https://shop.example/logo.png',
        ];

        $withLogo = (new LocalBusinessNodeProvider(
            $this->systemConfig($base),
            new IdFactory()
        ))->provide($this->makeContext())[0];
        static::assertSame('https://shop.example/logo.png', $withLogo['image']);

        $withPhoto = (new LocalBusinessNodeProvider(
            $this->systemConfig($base + [self::PREFIX . 'localBusinessImage' => 'https://shop.example/werk.jpg']),
            new IdFactory(),
        ))->provide($this->makeContext())[0];
        static::assertSame('https://shop.example/werk.jpg', $withPhoto['image']);
    }

    /**
     * Was: Öffnungszeiten mit Aufzählung und eine unlesbare Zeile.
     * Warum: Was sich nicht lesen lässt, darf nicht verloren gehen.
     * Erwartet: Aufzählung als zwei Tage, unlesbare Zeile als Text.
     */
    public function testOpeningHoursWithListAndUnreadableLine(): void
    {
        [$specification, $unreadable] = LocalBusinessNodeProvider::openingHoursSpecification([
            'Mo,We 8:00-12:00',
            'nach Vereinbarung',
        ]);

        static::assertSame(
            [
                'https://schema.org/Monday',
                'https://schema.org/Wednesday',
            ],
            $specification[0]['dayOfWeek']
        );
        static::assertSame('08:00', $specification[0]['opens']);
        static::assertSame(['nach Vereinbarung'], $unreadable);
    }

    public function testOmitsGeoWhenIncomplete(): void
    {
        $provider = new LocalBusinessNodeProvider(
            $this->systemConfig([
                self::PREFIX . 'localBusinessStreet' => 'Musterstr. 1',
                self::PREFIX . 'localBusinessCity' => 'Bochum',
                self::PREFIX . 'localBusinessLatitude' => '51.48',
            ]),
            new IdFactory(),
        );

        $node = $provider->provide($this->makeContext())[0];
        static::assertArrayNotHasKey('geo', $node);
        static::assertArrayNotHasKey('telephone', $node);
        static::assertArrayNotHasKey('openingHours', $node);
    }

    /**
     * Ein Komma aus der deutschen Schreibweise oder ein Wert außerhalb der Erde ergäbe ungültige Koordinaten.
     */
    public function testOmitsGeoForUnreadableCoordinates(): void
    {
        foreach ([['51,48', '7.21'], ['51.48', '200'], ['Bochum', '7.21']] as [$latitude, $longitude]) {
            $provider = new LocalBusinessNodeProvider(
                $this->systemConfig([
                    self::PREFIX . 'localBusinessStreet' => 'Musterstr. 1',
                    self::PREFIX . 'localBusinessCity' => 'Bochum',
                    self::PREFIX . 'localBusinessLatitude' => $latitude,
                    self::PREFIX . 'localBusinessLongitude' => $longitude,
                ]),
                new IdFactory(),
            );

            static::assertArrayNotHasKey(
                'geo',
                $provider->provide($this->makeContext())[0],
                $latitude . ' / ' . $longitude
            );
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function systemConfig(array $values): SystemConfigService
    {
        $service = $this->createMock(SystemConfigService::class);
        $service->method('getString')->willReturnCallback(
            static fn (string $key): string => $values[$key] ?? '',
        );

        return $service;
    }
}
