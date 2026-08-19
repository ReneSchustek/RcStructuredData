<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Enrichment;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Ruhrcoder\RcStructuredData\Schema\Enrichment\YoutubeApiMetadataEnricher;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YoutubeApiMetadataEnricherTest extends TestCase
{
    private const CONFIG_KEY = 'RcStructuredData.config.youtubeApiKey';

    public function testReturnsNullWithoutApiKey(): void
    {
        $client = new MockHttpClient([]);
        $enricher = $this->enricher($client, '');

        static::assertNull($enricher->enrich('abc123'));
        static::assertSame(0, $client->getRequestsCount());
    }

    public function testReturnsNullForEmptyVideoId(): void
    {
        $client = new MockHttpClient([]);

        static::assertNull($this->enricher($client, 'KEY')->enrich(''));
        static::assertSame(0, $client->getRequestsCount());
    }

    public function testMapsApiResponseToMetadata(): void
    {
        $client = new MockHttpClient([$this->videoResponse()]);

        $result = $this->enricher($client, 'KEY')->enrich('abc123');

        static::assertNotNull($result);
        static::assertSame('Mein Video', $result['name']);
        static::assertSame('Beschreibung', $result['description']);
        static::assertSame('2024-05-01T10:00:00Z', $result['uploadDate']);
        static::assertSame('PT5M30S', $result['duration']);
    }

    public function testCachesResponseAcrossCalls(): void
    {
        // Nur EINE Antwort im Mock: ein zweiter HTTP-Aufruf würde fehlschlagen.
        $client = new MockHttpClient([$this->videoResponse()]);
        $enricher = $this->enricher($client, 'KEY');

        $first = $enricher->enrich('abc123');
        $second = $enricher->enrich('abc123');

        static::assertSame($first, $second);
        static::assertSame(1, $client->getRequestsCount());
    }

    public function testReturnsNullWhenNoItems(): void
    {
        $client = new MockHttpClient([new MockResponse(json_encode(['items' => []], JSON_THROW_ON_ERROR), ['http_code' => 200])]);

        static::assertNull($this->enricher($client, 'KEY')->enrich('abc123'));
    }

    public function testSilentFallbackOnErrorStatus(): void
    {
        $client = new MockHttpClient([new MockResponse('quota exceeded', ['http_code' => 403])]);

        static::assertNull($this->enricher($client, 'KEY')->enrich('abc123'));
    }

    public function testSilentFallbackOnTransportError(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['error' => 'timeout'])]);

        static::assertNull($this->enricher($client, 'KEY')->enrich('abc123'));
    }

    public function testNegativelyCachesErrorStatusAcrossCalls(): void
    {
        // Nach einem 403 (Quota) darf ein zweiter enrich() KEINEN weiteren HTTP-Call auslösen —
        // sonst würde ein quota-erschöpfter API-Zustand jeden Cache-Miss-Render erneut mit einem
        // blockierenden, scheiternden Call belasten. Nur EINE Antwort im Mock: ein zweiter
        // Aufruf schlüge fehl, wenn der Fehler nicht negativ gecacht würde.
        $client = new MockHttpClient([new MockResponse('quota exceeded', ['http_code' => 403])]);
        $enricher = $this->enricher($client, 'KEY');

        static::assertNull($enricher->enrich('abc123'));
        static::assertNull($enricher->enrich('abc123'));
        static::assertSame(1, $client->getRequestsCount());
    }

    public function testNeverLeaksApiKeyIntoLog(): void
    {
        $apiKey = 'SUPER-SECRET-KEY-123';
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $client = new MockHttpClient([new MockResponse('', ['error' => 'connect to ' . $apiKey])]);
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn($apiKey);

        $enricher = new YoutubeApiMetadataEnricher($client, $systemConfig, new ArrayAdapter(), $logger);
        $result = $enricher->enrich('abc123');

        static::assertNull($result);
        static::assertNotEmpty($logger->messages);
        foreach ($logger->messages as $message) {
            static::assertStringNotContainsString($apiKey, $message);
        }
    }

    private function videoResponse(): MockResponse
    {
        $body = json_encode([
            'items' => [[
                'snippet' => [
                    'title' => 'Mein Video',
                    'description' => 'Beschreibung',
                    'publishedAt' => '2024-05-01T10:00:00Z',
                ],
                'contentDetails' => [
                    'duration' => 'PT5M30S',
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        return new MockResponse($body, ['http_code' => 200]);
    }

    private function enricher(HttpClientInterface $client, string $apiKey): YoutubeApiMetadataEnricher
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getString')->with(self::CONFIG_KEY)->willReturn($apiKey);

        return new YoutubeApiMetadataEnricher($client, $systemConfig, new ArrayAdapter(), new NullLogger());
    }
}
