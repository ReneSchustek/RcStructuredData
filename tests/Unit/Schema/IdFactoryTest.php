<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;

/**
 * Sichert die "@id"-Konventionen: Singletons an der Basis-URL, Seiten-Knoten an der Seiten-URL.
 */
class IdFactoryTest extends TestCase
{
    use SchemaContextTrait;

    public function testSingletonsHangAtBaseUrl(): void
    {
        $factory = new IdFactory();
        $context = $this->makeContext(baseUrl: 'https://shop.example/');

        static::assertSame('https://shop.example/#/schema/organization/1', $factory->organization($context));
        static::assertSame('https://shop.example/#/schema/website/1', $factory->website($context));
        static::assertSame('https://shop.example/#/schema/localbusiness/1', $factory->localBusiness($context));
    }

    public function testPageBoundIdsHangAtPageUrl(): void
    {
        $factory = new IdFactory();
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings');

        static::assertSame('https://shop.example/fittings', $factory->page($context));
        static::assertSame('https://shop.example/fittings#breadcrumb', $factory->breadcrumb($context));
        static::assertSame('https://shop.example/fittings#faq', $factory->faq($context));
        static::assertSame('https://shop.example/fittings#itemlist', $factory->itemList($context));
    }

    public function testVideoIdsAreUniquePerIndex(): void
    {
        $factory = new IdFactory();
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings');

        static::assertSame('https://shop.example/fittings#video-1', $factory->video($context, 1));
        static::assertSame('https://shop.example/fittings#video-2', $factory->video($context, 2));
        static::assertNotSame($factory->video($context, 1), $factory->video($context, 2));
    }
}
