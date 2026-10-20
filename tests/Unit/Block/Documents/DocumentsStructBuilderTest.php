<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Block\Documents;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\Documents\DocumentsStructBuilder;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Shopware\Core\Content\Media\MediaCollection;

/**
 * Prüft, wie aus gepflegten Einträgen und geladenen Medien die Liste der Nachweise wird. Ein Eintrag, dessen Datei
 * gelöscht ist, fällt weg, statt auf eine tote Adresse zu zeigen.
 */
class DocumentsStructBuilderTest extends TestCase
{
    use CmsFixtureTrait;

    private const PDF_ID = '0190a1b2c3d4e5f60718293a4b5c6d7e';
    private const GONE_ID = '0190a1b2c3d4e5f60718293a4b5c6d7f';

    public function testResolvesTheFileToItsAddress(): void
    {
        $slot = $this->makeDocumentsSlot([[
            'name' => 'Prüfzeugnis',
            'mediaId' => self::PDF_ID,
            'expires' => '2031-07-03',
        ]], 'Nachweise');
        $media = new MediaCollection([$this->makeMedia(self::PDF_ID, 'https://shop.example/media/abp.pdf')]);

        $struct = $this->builder()->build($slot, $media);

        static::assertSame('Nachweise', $struct->getTitle());
        static::assertSame([[
            'name' => 'Prüfzeugnis',
            'description' => '',
            'url' => 'https://shop.example/media/abp.pdf',
            'encodingFormat' => 'application/pdf',
            'datePublished' => '',
            'expires' => '2031-07-03',
        ]], $struct->getDocuments());
    }

    /**
     * Eine gelöschte Datei wäre auf der Seite ein toter Verweis und im Schema ein Beleg, den es
     * nicht gibt.
     */
    public function testEntryWithDeletedFileIsDropped(): void
    {
        $slot = $this->makeDocumentsSlot([
            ['name' => 'Gelöscht', 'mediaId' => self::GONE_ID],
            ['name' => 'Vorhanden', 'mediaId' => self::PDF_ID],
        ]);
        $media = new MediaCollection([$this->makeMedia(self::PDF_ID, 'https://shop.example/media/abp.pdf')]);

        static::assertSame(['Vorhanden'], array_column($this->builder()->build($slot, $media)->getDocuments(), 'name'));
    }

    public function testNothingIsListedWithoutLoadedMedia(): void
    {
        $slot = $this->makeDocumentsSlot([['name' => 'Prüfzeugnis', 'mediaId' => self::PDF_ID]]);

        static::assertSame([], $this->builder()->build($slot, null)->getDocuments());
    }

    private function builder(): DocumentsStructBuilder
    {
        return new DocumentsStructBuilder(new DocumentsCmsBlockExtractor());
    }
}
