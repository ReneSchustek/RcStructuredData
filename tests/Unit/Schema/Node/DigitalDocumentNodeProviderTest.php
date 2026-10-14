<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\Documents\Struct\DocumentsStruct;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DocumentsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\DigitalDocumentNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;

class DigitalDocumentNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testNotSupportedWithoutCmsPage(): void
    {
        static::assertFalse($this->provider()->supports($this->makeContext()));
    }

    /**
     * Das Beispiel von `/bauteile/glashalter/`: Prüfzeugnis und Verlängerungsbescheid.
     */
    public function testBuildsOneNodePerDocument(): void
    {
        $page = $this->makeCmsPage($this->resolvedSlot([
            $this->document('Allgemeines bauaufsichtliches Prüfzeugnis P-169908-LGA', 'https://shop.example/media/abp.pdf', expires: '2031-07-03', description: 'Absturzsichernde Verglasung, Kategorie C1'),
            $this->document('Verlängerungsbescheid 2026', 'https://shop.example/media/bescheid-2026.pdf', datePublished: '2026-07-04', expires: '2031-07-03'),
        ]));
        $context = $this->makeContext(pageUrl: 'https://shop.example/bauteile/glashalter/', cmsPage: $page);

        static::assertTrue($this->provider()->supports($context));

        $nodes = $this->provider()->provide($context);

        static::assertSame([
            '@type' => 'DigitalDocument',
            '@id' => 'https://shop.example/bauteile/glashalter/#dokument-1',
            'name' => 'Allgemeines bauaufsichtliches Prüfzeugnis P-169908-LGA',
            'url' => 'https://shop.example/media/abp.pdf',
            'description' => 'Absturzsichernde Verglasung, Kategorie C1',
            'encodingFormat' => 'application/pdf',
            'expires' => '2031-07-03',
        ], $nodes[0]);
        static::assertSame('https://shop.example/bauteile/glashalter/#dokument-2', $nodes[1]['@id']);
        static::assertSame('2026-07-04', $nodes[1]['datePublished']);
    }

    /**
     * Ein leeres `expires` hieße für einen Leser des Graphen nicht „unbefristet", sondern „kaputt".
     */
    public function testOptionalFieldsAreLeftOutWhenEmpty(): void
    {
        $page = $this->makeCmsPage($this->resolvedSlot([
            $this->document('Nachweis', 'https://shop.example/media/x.pdf', encodingFormat: ''),
        ]));

        $node = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page))[0];

        static::assertSame(['@type', '@id', 'name', 'url'], array_keys($node));
    }

    public function testNumberingRunsAcrossBlocks(): void
    {
        $page = $this->makeCmsPage(
            $this->resolvedSlot([$this->document('Eins', 'https://shop.example/1.pdf')]),
            $this->makeSlot('text'),
            $this->resolvedSlot([$this->document('Zwei', 'https://shop.example/2.pdf')]),
        );

        $nodes = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page));

        static::assertSame(['https://shop.example/x#dokument-1', 'https://shop.example/x#dokument-2'], array_column($nodes, '@id'));
    }

    /**
     * Ohne aufgelöste Datei gibt es keine Adresse — und ohne Adresse keinen Nachweis.
     */
    public function testUnresolvedSlotProducesNoNode(): void
    {
        $page = $this->makeCmsPage($this->makeDocumentsSlot([
            ['name' => 'Prüfzeugnis', 'mediaId' => '0190a1b2c3d4e5f60718293a4b5c6d7e'],
        ]));

        static::assertSame([], $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page)));
    }

    public function testForeignSlotWithDocumentsDataIsIgnored(): void
    {
        $slot = $this->makeSlot('text');
        $slot->setData(new DocumentsStruct('', [$this->document('Fremd', 'https://shop.example/f.pdf')]));

        static::assertSame([], $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $this->makeCmsPage($slot))));
    }

    /**
     * @param list<array{name: string, description: string, url: string, encodingFormat: string, datePublished: string, expires: string}> $documents
     */
    private function resolvedSlot(array $documents): CmsSlotEntity
    {
        $slot = $this->makeDocumentsSlot();
        $slot->setData(new DocumentsStruct('', $documents));

        return $slot;
    }

    /**
     * @return array{name: string, description: string, url: string, encodingFormat: string, datePublished: string, expires: string}
     */
    private function document(
        string $name,
        string $url,
        string $datePublished = '',
        string $expires = '',
        string $description = '',
        string $encodingFormat = 'application/pdf',
    ): array {
        return [
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'encodingFormat' => $encodingFormat,
            'datePublished' => $datePublished,
            'expires' => $expires,
        ];
    }

    private function provider(): DigitalDocumentNodeProvider
    {
        return new DigitalDocumentNodeProvider(new CmsSlotIterator(), new DocumentsCmsBlockExtractor(), new IdFactory());
    }
}
