<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Extractor;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;

class YoutubeVideoExtractorTest extends TestCase
{
    use CmsFixtureTrait;

    public function testSupportsOnlyYoutubeVideoSlots(): void
    {
        $extractor = new YoutubeVideoExtractor();

        static::assertTrue($extractor->supports($this->makeYoutubeSlot('abc123')));
        static::assertFalse($extractor->supports($this->makeSlot('text')));
    }

    public function testDerivesUrlsFromVideoId(): void
    {
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123'))[0];

        static::assertSame('abc123', $video['videoId']);
        static::assertSame('https://www.youtube.com/embed/abc123', $video['embedUrl']);
        static::assertSame('https://www.youtube.com/watch?v=abc123', $video['contentUrl']);
    }

    public function testEmptyVideoIdYieldsNoVideo(): void
    {
        static::assertSame([], (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('')));
        static::assertSame([], (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot(null)));
    }

    public function testUsesPreviewMediaThumbnailWhenPresent(): void
    {
        $slot = $this->makeYoutubeSlot('abc123', [], 'https://shop.example/media/preview.jpg');

        $video = (new YoutubeVideoExtractor())->extract($slot)[0];

        static::assertSame('https://shop.example/media/preview.jpg', $video['thumbnailUrl']);
    }

    public function testFallsBackToYoutubeThumbnailWithoutPreviewMedia(): void
    {
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123'))[0];

        static::assertSame('https://img.youtube.com/vi/abc123/hqdefault.jpg', $video['thumbnailUrl']);
    }

    public function testReadsSchemaConfigFields(): void
    {
        $slot = $this->makeYoutubeSlot('abc123', [
            'rcSchemaName' => ' Mein Video ',
            'rcSchemaDescription' => 'Beschreibung',
            'rcSchemaUploadDate' => '2024-05-01',
            'rcSchemaDuration' => 'PT5M30S',
        ]);

        $video = (new YoutubeVideoExtractor())->extract($slot)[0];

        static::assertSame('Mein Video', $video['name']);
        static::assertSame('Beschreibung', $video['description']);
        static::assertSame('2024-05-01', $video['uploadDate']);
        static::assertSame('PT5M30S', $video['duration']);
    }

    public function testMissingSchemaConfigFieldsAreEmptyStrings(): void
    {
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123'))[0];

        static::assertSame('', $video['name']);
        static::assertSame('', $video['description']);
        static::assertSame('', $video['uploadDate']);
        static::assertSame('', $video['duration']);
    }
}
