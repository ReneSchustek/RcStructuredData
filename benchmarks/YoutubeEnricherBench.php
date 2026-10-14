<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Benchmarks;

use PhpBench\Attributes as Bench;
use Psr\Log\NullLogger;
use Ruhrcoder\RcStructuredData\Benchmarks\Support\BenchmarkConfig;
use Ruhrcoder\RcStructuredData\Schema\Enrichment\YoutubeApiMetadataEnricher;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Der Abgleich der Videoangaben mit der YouTube-Schnittstelle, mit leerem und mit gefülltem Speicher.
 *
 * Er läuft mitten im Aufbau der Start-, Kategorie- oder Landingpage, je Video, dem nach Baustein und
 * Seite noch eine Angabe fehlt. Bei leerem Speicher ist das ein echter Netzaufruf; gemessen wird er
 * mit einem ungültigen Schlüssel. Die Schnittstelle antwortet dann mit einem Fehler, und genau dieser
 * Weg, Aufruf und Fehlerspeicher, ist der teure. Der Schlüssel des Shops wird dafür nicht gebraucht.
 */
#[Bench\Revs(3)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class YoutubeEnricherBench
{
    private const VIDEO_ID = 'dQw4w9WgXcQ';

    private YoutubeApiMetadataEnricher $enricher;

    public function setUpEmptyCache(): void
    {
        $client = new TraceableHttpClient(HttpClient::create());
        $this->enricher = $this->enricher(new NullAdapter(), $client);

        // Gegenprobe: Ohne Netzaufruf mäße die Reihe nur den Speicher.
        $this->enricher->enrich(self::VIDEO_ID);
        if (\count($client->getTracedRequests()) !== 1) {
            throw new RuntimeException('Der Abgleich hat die Schnittstelle nicht gefragt; gemessen würde nichts.');
        }
    }

    public function setUpFilledCache(): void
    {
        $this->enricher = $this->enricher(new ArrayAdapter(), HttpClient::create());
        $this->enricher->enrich(self::VIDEO_ID);
    }

    #[Bench\BeforeMethods('setUpEmptyCache')]
    public function benchEmptyCache(): void
    {
        $this->enricher->enrich(self::VIDEO_ID);
    }

    #[Bench\BeforeMethods('setUpFilledCache')]
    public function benchFilledCache(): void
    {
        $this->enricher->enrich(self::VIDEO_ID);
    }

    private function enricher(CacheInterface $cache, HttpClientInterface $client): YoutubeApiMetadataEnricher
    {
        return new YoutubeApiMetadataEnricher($client, new BenchmarkConfig(), $cache, new NullLogger());
    }
}
