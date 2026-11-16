<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Enrichment\VideoMetadataEnricher;
use Ruhrcoder\RcStructuredData\Schema\Product\ProductVideoObject;
use Ruhrcoder\RcStructuredData\Twig\ProductVideoExtension;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * @internal
 */
#[CoversClass(ProductVideoObject::class)]
#[CoversClass(ProductVideoExtension::class)]
final class ProductVideoObjectTest extends TestCase
{
    private const VIDEO_ID = 'dQw4w9WgXcQ';

    public function testBuildsVideoObjectFromApiMetadata(): void
    {
        $node = $this->builder([
            'name' => 'Montage Glasgeländer',
            'description' => 'So wird der Bausatz montiert.',
            'uploadDate' => '2024-05-02T08:00:00Z',
            'duration' => 'PT4M12S',
        ])->forProduct(self::VIDEO_ID, $this->product('Glasgeländer Bausatz'));

        static::assertNotNull($node);
        static::assertSame('VideoObject', $node['@type']);
        static::assertSame('Montage Glasgeländer', $node['name']);
        static::assertSame('So wird der Bausatz montiert.', $node['description']);
        static::assertSame('2024-05-02T08:00:00Z', $node['uploadDate']);
        static::assertSame('PT4M12S', $node['duration']);
        static::assertSame('https://www.youtube.com/embed/' . self::VIDEO_ID, $node['embedUrl']);
        static::assertSame('https://www.youtube.com/watch?v=' . self::VIDEO_ID, $node['contentUrl']);
        static::assertSame('https://img.youtube.com/vi/' . self::VIDEO_ID . '/hqdefault.jpg', $node['thumbnailUrl']);
    }

    public function testFallsBackToProductNameAndOmitsEmptyDuration(): void
    {
        $node = $this->builder(['name' => '', 'description' => '', 'uploadDate' => '2024-05-02', 'duration' => ''])
            ->forProduct(self::VIDEO_ID, $this->product('Glasgeländer Bausatz'));

        static::assertNotNull($node);
        static::assertSame('Glasgeländer Bausatz', $node['name']);
        static::assertSame('Glasgeländer Bausatz', $node['description']);
        static::assertArrayNotHasKey('duration', $node);
    }

    public function testNoNodeWithoutUploadDate(): void
    {
        $node = $this->builder(['name' => 'Video', 'description' => '', 'uploadDate' => '', 'duration' => ''])
            ->forProduct(self::VIDEO_ID, $this->product('Bausatz'));

        static::assertNull($node);
    }

    public function testNoNodeWithoutApiAnswer(): void
    {
        static::assertNull($this->builder(null)->forProduct(self::VIDEO_ID, $this->product('Bausatz')));
    }

    public function testNoNodeForMissingOrInvalidVideoId(): void
    {
        $builder = $this->builder(['name' => 'Video', 'description' => '', 'uploadDate' => '2024-05-02', 'duration' => '']);

        static::assertNull($builder->forProduct(null, $this->product('Bausatz')));
        static::assertNull($builder->forProduct('https://www.youtube.com/watch?v=' . self::VIDEO_ID, $this->product('Bausatz')));
    }

    public function testTwigFunctionIsRegistered(): void
    {
        $functions = (new ProductVideoExtension($this->builder(null)))->getFunctions();

        static::assertSame('rc_schema_product_video', $functions[0]->getName());
    }

    /**
     * @param array{name: string, description: string, uploadDate: string, duration: string}|null $meta
     */
    private function builder(?array $meta): ProductVideoObject
    {
        $enricher = $this->createStub(VideoMetadataEnricher::class);
        $enricher->method('enrich')->willReturn($meta);

        return new ProductVideoObject($enricher);
    }

    private function product(string $name): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setName($name);
        $product->setTranslated(['name' => $name]);

        return $product;
    }
}
