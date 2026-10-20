<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Extractor;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;

/**
 * Prüft das Lesen des YouTube-Elements: Adressen aus der Kennung, Vorschaubild aus dem Element oder von YouTube, die
 * gepflegten Zusatzfelder. Eine ungültige Kennung ergibt kein Video.
 */
class YoutubeVideoExtractorTest extends TestCase
{
    use CmsFixtureTrait;

    public function testSupportsOnlyYoutubeVideoSlots(): void
    {
        $extractor = new YoutubeVideoExtractor();

        static::assertTrue($extractor->supports($this->makeYoutubeSlot('abc123DEF45')));
        static::assertFalse($extractor->supports($this->makeSlot('text')));
    }

    public function testDerivesUrlsFromVideoId(): void
    {
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123DEF45'))[0];

        static::assertSame('abc123DEF45', $video['videoId']);
        static::assertSame('https://www.youtube.com/embed/abc123DEF45', $video['embedUrl']);
        static::assertSame('https://www.youtube.com/watch?v=abc123DEF45', $video['contentUrl']);
    }

    public function testEmptyVideoIdYieldsNoVideo(): void
    {
        static::assertSame([], (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('')));
        static::assertSame([], (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot(null)));
    }

    /**
     * Eine eingefügte Adresse oder eine Kennung mit falscher Länge ergäbe ein Video, das es nicht gibt.
     */
    public function testInvalidVideoIdYieldsNoVideo(): void
    {
        static::assertSame(
            [],
            (new YoutubeVideoExtractor())->extract(
                $this->makeYoutubeSlot('https://youtu.be/abc123DEF45')
            )
        );
        static::assertSame([], (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123')));
    }

    public function testUsesPreviewMediaThumbnailWhenPresent(): void
    {
        $slot = $this->makeYoutubeSlot('abc123DEF45', [], 'https://shop.example/media/preview.jpg');

        $video = (new YoutubeVideoExtractor())->extract($slot)[0];

        static::assertSame('https://shop.example/media/preview.jpg', $video['thumbnailUrl']);
    }

    public function testFallsBackToYoutubeThumbnailWithoutPreviewMedia(): void
    {
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123DEF45'))[0];

        static::assertSame('https://img.youtube.com/vi/abc123DEF45/hqdefault.jpg', $video['thumbnailUrl']);
    }

    public function testReadsSchemaConfigFields(): void
    {
        $slot = $this->makeYoutubeSlot('abc123DEF45', [
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
        $video = (new YoutubeVideoExtractor())->extract($this->makeYoutubeSlot('abc123DEF45'))[0];

        static::assertSame('', $video['name']);
        static::assertSame('', $video['description']);
        static::assertSame('', $video['uploadDate']);
        static::assertSame('', $video['duration']);
    }
}
