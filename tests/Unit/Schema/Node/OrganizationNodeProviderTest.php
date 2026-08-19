<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\OrganizationNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrganizationNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testUsesConfiguredNameAndOptionalFields(): void
    {
        $provider = new OrganizationNodeProvider(
            $this->systemConfig([
                'RcStructuredData.config.organizationName' => 'Ruhrcoder GmbH',
                'RcStructuredData.config.organizationLogo' => 'https://shop.example/logo.png',
                'RcStructuredData.config.organizationSameAs' => "https://facebook.com/x\nhttps://instagram.com/x",
            ]),
            new IdFactory(),
        );
        $context = $this->makeContext(baseUrl: 'https://shop.example/');

        static::assertTrue($provider->supports($context));

        $node = $provider->provide($context)[0];
        static::assertSame('Organization', $node['@type']);
        static::assertSame('https://shop.example/#/schema/organization/1', $node['@id']);
        static::assertSame('Ruhrcoder GmbH', $node['name']);
        static::assertSame('https://shop.example/logo.png', $node['logo']);
        static::assertSame(['https://facebook.com/x', 'https://instagram.com/x'], $node['sameAs']);
    }

    public function testFallsBackToShopName(): void
    {
        $provider = new OrganizationNodeProvider(
            $this->systemConfig(['core.basicInformation.shopName' => 'Demo Shop']),
            new IdFactory(),
        );
        $context = $this->makeContext();

        static::assertTrue($provider->supports($context));
        static::assertSame('Demo Shop', $provider->provide($context)[0]['name']);
    }

    public function testNotSupportedWithoutAnyName(): void
    {
        $provider = new OrganizationNodeProvider($this->systemConfig([]), new IdFactory());

        static::assertFalse($provider->supports($this->makeContext()));
    }

    public function testOmitsEmptyOptionalFields(): void
    {
        $provider = new OrganizationNodeProvider(
            $this->systemConfig(['RcStructuredData.config.organizationName' => 'Ruhrcoder']),
            new IdFactory(),
        );

        $node = $provider->provide($this->makeContext())[0];
        static::assertArrayNotHasKey('logo', $node);
        static::assertArrayNotHasKey('sameAs', $node);
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
