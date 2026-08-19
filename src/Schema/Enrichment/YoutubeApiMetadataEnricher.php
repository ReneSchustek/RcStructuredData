<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Enrichment;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Holt fehlende VideoObject-Metadaten über die YouTube-Data-API (videos.list).
 *
 * Greift nur bei gesetztem API-Key. Antworten werden gecacht (Quota-Schonung), Netzwerk-/Quota-
 * Fehler führen zu `null` (stiller Fallback auf die manuelle Pflege) — nie zu einem Seitenfehler.
 * Der API-Key stammt aus `system_config` und taucht weder im Output noch im Log auf.
 */
final class YoutubeApiMetadataEnricher implements VideoMetadataEnricher
{
    private const CONFIG_KEY = 'RcStructuredData.config.youtubeApiKey';
    private const API_URL = 'https://www.googleapis.com/youtube/v3/videos';
    private const TIMEOUT_SECONDS = 3.0;
    private const CACHE_TTL = 86400;       // erfolgreicher Treffer: 1 Tag
    private const CACHE_TTL_MISS = 3600;   // „nicht gefunden": 1 Stunde
    private const CACHE_PREFIX = 'rc_schema_yt_';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfig,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(string $videoId): ?array
    {
        $apiKey = trim($this->systemConfig->getString(self::CONFIG_KEY));
        if ($apiKey === '' || $videoId === '') {
            return null;
        }

        try {
            // Cache-Schlüssel bindet an Video-ID und Key (Key-Rotation invalidiert den Cache);
            // hash() dient nur einem kollisionsarmen, PSR-6-tauglichen Schlüssel, nicht der Sicherheit.
            $cacheKey = self::CACHE_PREFIX . hash('sha256', $videoId . '|' . $apiKey);

            return $this->cache->get(
                $cacheKey,
                fn (ItemInterface $item): ?array => $this->fetchWithTtl($item, $videoId, $apiKey),
            );
        } catch (Throwable $exception) {
            // Sicherheitsnetz für Fehler der Cache-Infrastruktur selbst (der Netzwerk-/Quota-
            // Fehlerpfad wird bereits im Callback abgefangen und negativ gecacht).
            $this->logger->info(
                'YouTube-API-Anreicherung fehlgeschlagen: ' . $this->redactKey($exception->getMessage(), $apiKey),
            );

            return null;
        }
    }

    /**
     * @return array{name: string, description: string, uploadDate: string, duration: string}|null
     */
    private function fetchWithTtl(ItemInterface $item, string $videoId, string $apiKey): ?array
    {
        try {
            $data = $this->fetch($videoId, $apiKey);
        } catch (Throwable $exception) {
            // Netzwerk-/Quota-/Transportfehler (z. B. 403) NEGATIV cachen — sonst würde ein
            // defekter/quota-erschöpfter API-Zustand jeden HTTP-Cache-Miss-Render erneut mit
            // einem bis zu TIMEOUT_SECONDS blockierenden, scheiternden Call belasten. Wird die
            // Exception dagegen aus dem Callback geworfen, speichert Symfony das Item nicht.
            // Der API-Key darf nie ins Log — Transport-Meldungen können die Request-URL
            // (inkl. ?key=…) enthalten, daher wird er aus der Meldung redigiert. Das Log läuft
            // nur beim Cache-Miss (nicht bei jedem Treffer auf den Negativ-Eintrag) → kein Spam.
            $item->expiresAfter(self::CACHE_TTL_MISS);
            $this->logger->info(
                'YouTube-API-Anreicherung fehlgeschlagen: ' . $this->redactKey($exception->getMessage(), $apiKey),
            );

            return null;
        }

        // Treffer lange, „nicht gefunden" kurz cachen (falls das Video später gepflegt wird).
        $item->expiresAfter($data === null ? self::CACHE_TTL_MISS : self::CACHE_TTL);

        return $data;
    }

    /**
     * @return array{name: string, description: string, uploadDate: string, duration: string}|null
     */
    private function fetch(string $videoId, string $apiKey): ?array
    {
        $response = $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'part' => 'snippet,contentDetails',
                'id' => $videoId,
                'key' => $apiKey,
            ],
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        // Nicht-200 (z. B. 403 Quota) wirft — der Aufrufer cacht dann nicht und fällt still zurück.
        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new RuntimeException('YouTube-API-Status ' . $status);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        $items = $payload['items'] ?? null;
        if (!\is_array($items) || $items === []) {
            return null;
        }

        $first = $items[0];
        if (!\is_array($first)) {
            return null;
        }

        $snippet = \is_array($first['snippet'] ?? null) ? $first['snippet'] : [];
        $contentDetails = \is_array($first['contentDetails'] ?? null) ? $first['contentDetails'] : [];

        return [
            'name' => $this->stringValue($snippet['title'] ?? null),
            'description' => $this->stringValue($snippet['description'] ?? null),
            'uploadDate' => $this->stringValue($snippet['publishedAt'] ?? null),
            'duration' => $this->stringValue($contentDetails['duration'] ?? null),
        ];
    }

    private function stringValue(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }

    private function redactKey(string $message, string $apiKey): string
    {
        return $apiKey === '' ? $message : str_replace($apiKey, '***', $message);
    }
}
