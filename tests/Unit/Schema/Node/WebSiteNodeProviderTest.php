<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\WebSiteNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class WebSiteNodeProviderTest extends TestCase
{
    use SchemaContextTrait;

    public function testLinksPublisherToOrganization(): void
    {
        $provider = new WebSiteNodeProvider(
            $this->systemConfig(['core.basicInformation.shopName' => 'Demo Shop']),
            new IdFactory(),
        );
        $context = $this->makeContext(baseUrl: 'https://shop.example/');

        $node = $provider->provide($context)[0];
        static::assertSame('WebSite', $node['@type']);
        static::assertSame('https://shop.example/#/schema/website/1', $node['@id']);
        static::assertSame('Demo Shop', $node['name']);
        static::assertSame('de-DE', $node['inLanguage']);
        static::assertSame(['@id' => 'https://shop.example/#/schema/organization/1'], $node['publisher']);
        static::assertArrayNotHasKey('potentialAction', $node);
    }

    public function testAddsSearchActionWhenTemplateValid(): void
    {
        $provider = new WebSiteNodeProvider(
            $this->systemConfig([
                'RcStructuredData.config.websiteName' => 'Demo',
                'RcStructuredData.config.websiteSearchUrl' => 'https://shop.example/search?search={search_term_string}',
            ]),
            new IdFactory(),
        );

        $node = $provider->provide($this->makeContext())[0];
        static::assertArrayHasKey('potentialAction', $node);
        static::assertSame('SearchAction', $node['potentialAction']['@type']);
        static::assertSame(
            'https://shop.example/search?search={search_term_string}',
            $node['potentialAction']['target']['urlTemplate'],
        );
    }

    public function testIgnoresSearchUrlWithoutPlaceholder(): void
    {
        $provider = new WebSiteNodeProvider(
            $this->systemConfig([
                'RcStructuredData.config.websiteName' => 'Demo',
                'RcStructuredData.config.websiteSearchUrl' => 'https://shop.example/search',
            ]),
            new IdFactory(),
        );

        static::assertArrayNotHasKey('potentialAction', $provider->provide($this->makeContext())[0]);
    }

    public function testNotSupportedWithoutName(): void
    {
        $provider = new WebSiteNodeProvider($this->systemConfig([]), new IdFactory());

        static::assertFalse($provider->supports($this->makeContext()));
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
