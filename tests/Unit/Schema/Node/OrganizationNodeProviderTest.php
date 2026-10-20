<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\OrganizationNodeProvider;
use Ruhrcoder\RcStructuredData\Schema\OrganizationDetails;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Prüft die Firmenangaben: Name aus der Einstellung oder dem Shop, Kontaktpunkt aus Telefon und Shop-Adresse,
 * Rückgaberichtlinie nur mit Frist und Ländern.
 */
class OrganizationNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testUsesConfiguredNameAndOptionalFields(): void
    {
        $provider = $this->provider($this->systemConfig([
                'RcStructuredData.config.organizationName' => 'Ruhrcoder GmbH',
                'RcStructuredData.config.organizationLogo' => 'https://shop.example/logo.png',
                'RcStructuredData.config.organizationSameAs' => "https://facebook.com/x\nhttps://instagram.com/x",
            ]));
        $context = $this->makeContext(baseUrl: 'https://shop.example/');

        static::assertTrue($provider->supports($context));

        $node = $provider->provide($context)[0];
        static::assertSame('Organization', $node['@type']);
        static::assertSame('https://shop.example/#organization', $node['@id']);
        static::assertSame('Ruhrcoder GmbH', $node['name']);
        static::assertSame('https://shop.example/logo.png', $node['logo']);
        static::assertSame(['https://facebook.com/x', 'https://instagram.com/x'], $node['sameAs']);
    }

    public function testFallsBackToShopName(): void
    {
        $provider = $this->provider($this->systemConfig(['core.basicInformation.shopName' => 'Demo Shop']));
        $context = $this->makeContext();

        static::assertTrue($provider->supports($context));
        static::assertSame('Demo Shop', $provider->provide($context)[0]['name']);
    }

    public function testNotSupportedWithoutAnyName(): void
    {
        $provider = $this->provider($this->systemConfig([]));

        static::assertFalse($provider->supports($this->makeContext()));
    }

    public function testOmitsEmptyOptionalFields(): void
    {
        $provider = $this->provider($this->systemConfig(['RcStructuredData.config.organizationName' => 'Ruhrcoder']));

        $node = $provider->provide($this->makeContext())[0];
        static::assertArrayNotHasKey('logo', $node);
        static::assertArrayNotHasKey('sameAs', $node);
        static::assertArrayNotHasKey('contactPoint', $node);
    }

    /**
     * Was: Telefon (Einstellung LocalBusiness) und Shop-E-Mail sind gepflegt.
     * Warum: Der Kontaktpunkt ersetzt den handgeschriebenen aus den Layouts; Quelle sind die vorhandenen Einstellungen.
     * Erwartet: ContactPoint mit Telefon, E-Mail, Kundendienst, Deutsch, DE/AT/CH.
     */
    public function testAddsContactPointFromPhoneAndShopEmail(): void
    {
        $provider = $this->provider($this->systemConfig([
                'RcStructuredData.config.organizationName' => 'Ruhrcoder',
                'RcStructuredData.config.localBusinessPhone' => '+49 234 123456',
                'core.basicInformation.email' => 'info@shop.example',
            ]));

        $node = $provider->provide($this->makeContext())[0];
        static::assertSame([
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'telephone' => '+49 234 123456',
            'email' => 'info@shop.example',
            'availableLanguage' => 'de',
            'areaServed' => ['DE', 'AT', 'CH'],
        ], $node['contactPoint']);
    }

    /**
     * Was: Telefon und Rückgaberichtlinie eingestellt.
     * Warum: Google verlangt an der Rückgaberichtlinie Land, Art und Frist; das Telefon gehört direkt an die
     * Organisation,
     *        nicht nur in den Kontaktpunkt.
     * Erwartet: `telephone` und eine vollständige `MerchantReturnPolicy` mit Rücksendekosten.
     */
    public function testAddsTelephoneAndReturnPolicy(): void
    {
        $node = $this->provider($this->systemConfig([
            'RcStructuredData.config.organizationName' => 'Ruhrcoder',
            'RcStructuredData.config.localBusinessPhone' => '+49 234 123456',
            'RcStructuredData.config.returnPolicyDays' => '14',
            'RcStructuredData.config.returnPolicyCountries' => 'de, at;CH xyz',
            'RcStructuredData.config.returnPolicyFees' => 'customer',
        ]))->provide($this->makeContext())[0];

        static::assertSame('+49 234 123456', $node['telephone']);
        static::assertSame([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => ['DE', 'AT', 'CH'],
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 14,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
        ], $node['hasMerchantReturnPolicy']);
    }

    /**
     * Was: Frist 0, keine Länder, unbekannte Kostenangabe.
     * Erwartet: keine Richtlinie ohne Frist oder Land; ohne bekannte Kostenangabe fehlt nur `returnFees`.
     */
    public function testReturnPolicyNeedsDaysAndCountries(): void
    {
        $details = new OrganizationDetails(
            $this->systemConfig([
                'RcStructuredData.config.returnPolicyCountries' => 'DE',
            ])
        );
        static::assertNull($details->returnPolicy(null));

        $details = new OrganizationDetails($this->systemConfig(['RcStructuredData.config.returnPolicyDays' => '14']));
        static::assertNull($details->returnPolicy(null));

        $details = new OrganizationDetails($this->systemConfig([
            'RcStructuredData.config.returnPolicyDays' => '30',
            'RcStructuredData.config.returnPolicyCountries' => 'DE',
            'RcStructuredData.config.returnPolicyFees' => 'unset',
        ]));
        $policy = $details->returnPolicy(null);
        static::assertNotNull($policy);
        static::assertSame('DE', $policy['applicableCountry']);
        static::assertSame(30, $policy['merchantReturnDays']);
        static::assertArrayNotHasKey('returnFees', $policy);
    }

    private function provider(SystemConfigService $systemConfig): OrganizationNodeProvider
    {
        return new OrganizationNodeProvider($systemConfig, new IdFactory(), new OrganizationDetails($systemConfig));
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
        $service->method('getInt')->willReturnCallback(
            static fn (string $key): int => (int) ($values[$key] ?? 0),
        );

        return $service;
    }
}
