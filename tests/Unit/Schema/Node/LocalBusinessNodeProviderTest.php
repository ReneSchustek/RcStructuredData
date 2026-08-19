<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\LocalBusinessNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\System\SystemConfig\SystemConfigService;

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
                self::PREFIX . 'organizationName' => 'Trummer Edelstahl GmbH',
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
        static::assertSame('LocalBusiness', $node['@type']);
        static::assertSame('https://shop.example/#/schema/localbusiness/1', $node['@id']);
        static::assertSame('Trummer Edelstahl GmbH', $node['name']);
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
        static::assertSame(['Mo-Fr 09:00-18:00', 'Sa 10:00-14:00'], $node['openingHours']);
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
