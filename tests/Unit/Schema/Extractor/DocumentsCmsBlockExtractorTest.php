<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Extractor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;

class DocumentsCmsBlockExtractorTest extends TestCase
{
    use CmsFixtureTrait;

    private const MEDIA_ID = '0190a1b2c3d4e5f60718293a4b5c6d7e';

    public function testSupportsOnlyItsOwnElement(): void
    {
        $extractor = new DocumentsCmsBlockExtractor();

        static::assertTrue($extractor->supports($this->makeDocumentsSlot()));
        static::assertFalse($extractor->supports($this->makeSlot('text')));
    }

    public function testReadsAMaintainedDocument(): void
    {
        $slot = $this->makeDocumentsSlot([[
            'name' => '  Prüfzeugnis P-169908-LGA ',
            'description' => 'Absturzsichernde Verglasung',
            'mediaId' => self::MEDIA_ID,
            'datePublished' => '2016-07-04',
            'expires' => '2031-07-03',
        ]], 'Prüfzeugnisse');

        $extractor = new DocumentsCmsBlockExtractor();

        static::assertSame([[
            'name' => 'Prüfzeugnis P-169908-LGA',
            'description' => 'Absturzsichernde Verglasung',
            'mediaId' => self::MEDIA_ID,
            'datePublished' => '2016-07-04',
            'expires' => '2031-07-03',
        ]], $extractor->extract($slot));
        static::assertSame('Prüfzeugnisse', $extractor->extractTitle($slot));
    }

    /**
     * Ein Nachweis ohne Namen ist ein nackter Verweis, einer ohne Datei ein Versprechen ohne Beleg.
     */
    public function testDropsEntriesWithoutNameOrFile(): void
    {
        $slot = $this->makeDocumentsSlot([
            ['name' => '', 'mediaId' => self::MEDIA_ID],
            ['name' => 'Ohne Datei', 'mediaId' => null],
            ['name' => 'Kaputte Kennung', 'mediaId' => 'keine-kennung'],
            'kein Eintrag',
            ['name' => 'Gültig', 'mediaId' => strtoupper(self::MEDIA_ID)],
        ]);

        $documents = (new DocumentsCmsBlockExtractor())->extract($slot);

        static::assertSame(['Gültig'], array_column($documents, 'name'));
        static::assertSame(self::MEDIA_ID, $documents[0]['mediaId']);
    }

    public function testMediaIdsAreUnique(): void
    {
        $slot = $this->makeDocumentsSlot([
            ['name' => 'Eins', 'mediaId' => self::MEDIA_ID],
            ['name' => 'Zwei', 'mediaId' => self::MEDIA_ID],
        ]);

        static::assertSame([self::MEDIA_ID], (new DocumentsCmsBlockExtractor())->extractMediaIds($slot));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function dateProvider(): array
    {
        return [
            'Tag bleibt Tag' => ['2031-07-03', '2031-07-03'],
            // Ein Datumsfeld kann „4. Juli, 0 Uhr hier" als UTC-Zeitpunkt speichern. Abgeschnitten
            // wäre das der 3. Juli: ein Prüfzeugnis, das einen Tag früher abläuft als darauf steht.
            'Mitternacht Sommerzeit als UTC' => ['2026-07-03T22:00:00.000Z', '2026-07-04'],
            'Mitternacht Winterzeit als UTC' => ['2026-01-03T23:00:00.000Z', '2026-01-04'],
            'Zeitpunkt mit Zone' => ['2026-07-04T00:00:00+02:00', '2026-07-04'],
            // Ohne Zone gilt die Uhrzeit der Redaktion, nicht die des Servers. Auf einem Server in UTC
            // wäre 23:30 sonst schon der nächste Tag.
            'Zeitpunkt ohne Zone' => ['2026-07-03 23:30:00', '2026-07-03'],
            'unmöglicher Tag' => ['2026-02-30', ''],
            'kein Datum' => ['demnächst', ''],
            'leer' => ['', ''],
            'nicht gesetzt' => [null, ''],
        ];
    }

    #[DataProvider('dateProvider')]
    public function testDatesAreNormalisedToTheEditorialDay(mixed $raw, string $expected): void
    {
        // Die Zeitzone des Prozesses fest auf UTC, wie auf vielen Servern: So urteilt der Test überall
        // gleich, und ein Datum, das von ihr abhinge, fiele auf.
        $previous = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $slot = $this->makeDocumentsSlot([
            ['name' => 'Nachweis', 'mediaId' => self::MEDIA_ID, 'expires' => $raw],
        ]);

        try {
            static::assertSame($expected, (new DocumentsCmsBlockExtractor())->extract($slot)[0]['expires']);
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
