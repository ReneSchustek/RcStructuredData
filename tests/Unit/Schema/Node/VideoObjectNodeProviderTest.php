<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Enrichment\VideoMetadataEnricher;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\VideoObjectNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;

class VideoObjectNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testNotSupportedWithoutCmsPage(): void
    {
        static::assertFalse($this->provider()->supports($this->makeContext()));
    }

    public function testBuildsOneVideoObjectPerSlotWithUniqueId(): void
    {
        $page = $this->makeCmsPage(
            $this->makeYoutubeSlot('vid-1'),
            $this->makeSlot('text'),
            $this->makeYoutubeSlot('vid-2'),
        );
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings', cmsPage: $page);

        $nodes = $this->provider()->provide($context);

        static::assertCount(2, $nodes);
        static::assertSame('VideoObject', $nodes[0]['@type']);
        static::assertSame('https://shop.example/fittings#video-1', $nodes[0]['@id']);
        static::assertSame('https://shop.example/fittings#video-2', $nodes[1]['@id']);
    }

    public function testPublisherReferencesOrganization(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1'));
        $context = $this->makeContext(baseUrl: 'https://shop.example/', cmsPage: $page);

        $node = $this->provider()->provide($context)[0];

        static::assertSame(
            ['@id' => 'https://shop.example/#/schema/organization/1'],
            $node['publisher'],
        );
    }

    public function testNameCascadePrefersBlockValue(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1', ['rcSchemaName' => 'Block-Titel']));
        $context = $this->makeContext(
            name: 'Seitentitel',
            cmsPage: $page,
            videoFallback: $this->fallback(name: 'Kategorie-Titel'),
        );

        static::assertSame('Block-Titel', $this->provider()->provide($context)[0]['name']);
    }

    public function testNameCascadeFallsBackToCategoryThenPage(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1'));

        $withCategory = $this->makeContext(
            name: 'Seitentitel',
            cmsPage: $page,
            videoFallback: $this->fallback(name: 'Kategorie-Titel'),
        );
        static::assertSame('Kategorie-Titel', $this->provider()->provide($withCategory)[0]['name']);

        $pageOnly = $this->makeContext(name: 'Seitentitel', cmsPage: $page);
        static::assertSame('Seitentitel', $this->provider()->provide($pageOnly)[0]['name']);
    }

    public function testUploadDateAndDurationOmittedWhenMissing(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1'));
        $context = $this->makeContext(cmsPage: $page);

        $node = $this->provider()->provide($context)[0];

        static::assertArrayNotHasKey('uploadDate', $node);
        static::assertArrayNotHasKey('duration', $node);
    }

    public function testUploadDateAndDurationFromBlock(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1', [
            'rcSchemaUploadDate' => '2024-05-01',
            'rcSchemaDuration' => 'PT5M30S',
        ]));
        $context = $this->makeContext(cmsPage: $page);

        $node = $this->provider()->provide($context)[0];

        static::assertSame('2024-05-01', $node['uploadDate']);
        static::assertSame('PT5M30S', $node['duration']);
    }

    public function testUploadDateFallsBackToCategory(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1'));
        $context = $this->makeContext(
            cmsPage: $page,
            videoFallback: $this->fallback(uploadDate: '2023-01-01'),
        );

        static::assertSame('2023-01-01', $this->provider()->provide($context)[0]['uploadDate']);
    }

    public function testApiFillsGapsAfterBlockAndCategory(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1'));
        $context = $this->makeContext(name: 'Seitentitel', cmsPage: $page);
        $enricher = $this->stubEnricher([
            'name' => 'API-Titel',
            'description' => 'API-Beschreibung',
            'uploadDate' => '2022-02-02',
            'duration' => 'PT2M',
        ]);

        $node = $this->provider($enricher)->provide($context)[0];

        // Block + Category leer → API greift vor dem Auto-Wert (Seitentitel).
        static::assertSame('API-Titel', $node['name']);
        static::assertSame('API-Beschreibung', $node['description']);
        static::assertSame('2022-02-02', $node['uploadDate']);
        static::assertSame('PT2M', $node['duration']);
    }

    public function testBlockWinsOverApi(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1', [
            'rcSchemaName' => 'Block-Titel',
            'rcSchemaUploadDate' => '2020-01-01',
            'rcSchemaDuration' => 'PT1M',
            'rcSchemaDescription' => 'Block-Text',
        ]));
        $context = $this->makeContext(cmsPage: $page);
        $enricher = $this->stubEnricher(['name' => 'API', 'description' => 'API', 'uploadDate' => '2099-01-01', 'duration' => 'PT9M']);

        $node = $this->provider($enricher)->provide($context)[0];

        static::assertSame('Block-Titel', $node['name']);
        static::assertSame('2020-01-01', $node['uploadDate']);
    }

    public function testApiNotCalledWhenAllFieldsPresent(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('vid-1', [
            'rcSchemaName' => 'N',
            'rcSchemaDescription' => 'D',
            'rcSchemaUploadDate' => '2020-01-01',
            'rcSchemaDuration' => 'PT1M',
        ]));
        $context = $this->makeContext(cmsPage: $page);

        // Enricher wirft, falls er doch aufgerufen wird — beweist das Quota-schonende Gating.
        $throwingEnricher = new class () implements VideoMetadataEnricher {
            public function enrich(string $videoId): ?array
            {
                throw new LogicException('Enricher darf hier nicht aufgerufen werden.');
            }
        };

        $node = $this->provider($throwingEnricher)->provide($context)[0];
        static::assertSame('N', $node['name']);
    }

    /**
     * @return array{name: string, description: string, uploadDate: string, duration: string}
     */
    private function fallback(string $name = '', string $description = '', string $uploadDate = '', string $duration = ''): array
    {
        return ['name' => $name, 'description' => $description, 'uploadDate' => $uploadDate, 'duration' => $duration];
    }

    /**
     * @param array{name: string, description: string, uploadDate: string, duration: string}|null $metadata
     */
    private function stubEnricher(?array $metadata): VideoMetadataEnricher
    {
        return new class ($metadata) implements VideoMetadataEnricher {
            /**
             * @param array{name: string, description: string, uploadDate: string, duration: string}|null $metadata
             */
            public function __construct(private readonly ?array $metadata)
            {
            }

            public function enrich(string $videoId): ?array
            {
                return $this->metadata;
            }
        };
    }

    private function provider(?VideoMetadataEnricher $enricher = null): VideoObjectNodeProvider
    {
        return new VideoObjectNodeProvider(
            new CmsSlotIterator(),
            new YoutubeVideoExtractor(),
            new IdFactory(),
            $enricher ?? $this->stubEnricher(null),
        );
    }
}
