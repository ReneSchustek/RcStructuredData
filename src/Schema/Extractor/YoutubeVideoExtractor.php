<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;

/**
 * Liest die Video-Rohdaten aus einem youtube-video-CMS-Slot.
 *
 * Einzige Parsing-Stelle für youtube-video: aus der `videoID` werden die YouTube-URLs
 * abgeleitet, das Thumbnail stammt aus dem gepflegten `previewMedia` (vom Core-Resolver als
 * ImageStruct an den Slot gehängt) oder dem YouTube-Standardbild. Die vier per Admin-Override
 * ergänzten Metadatenfelder werden roh durchgereicht — die Lücken-Füll-Kaskade (Category/Seite)
 * liegt bewusst im VideoObjectNodeProvider, weil dort der Seiten-Kontext verfügbar ist.
 */
final class YoutubeVideoExtractor implements CmsBlockSchemaExtractor
{
    public const CMS_ELEMENT_TYPE = 'youtube-video';

    private const CONFIG_VIDEO_ID = 'videoID';
    private const CONFIG_NAME = 'rcSchemaName';
    private const CONFIG_DESCRIPTION = 'rcSchemaDescription';
    private const CONFIG_UPLOAD_DATE = 'rcSchemaUploadDate';
    private const CONFIG_DURATION = 'rcSchemaDuration';

    public function supports(CmsSlotEntity $slot): bool
    {
        return $slot->getType() === self::CMS_ELEMENT_TYPE;
    }

    /**
     * @return list<array{videoId: string, embedUrl: string, contentUrl: string, thumbnailUrl: string, name: string, description: string, uploadDate: string, duration: string}>
     */
    public function extract(CmsSlotEntity $slot): array
    {
        $videoId = $this->configString($slot, self::CONFIG_VIDEO_ID);
        if ($videoId === '') {
            return [];
        }

        return [[
            'videoId' => $videoId,
            'embedUrl' => 'https://www.youtube.com/embed/' . $videoId,
            'contentUrl' => 'https://www.youtube.com/watch?v=' . $videoId,
            'thumbnailUrl' => $this->resolveThumbnailUrl($slot, $videoId),
            'name' => $this->configString($slot, self::CONFIG_NAME),
            'description' => $this->configString($slot, self::CONFIG_DESCRIPTION),
            'uploadDate' => $this->configString($slot, self::CONFIG_UPLOAD_DATE),
            'duration' => $this->configString($slot, self::CONFIG_DURATION),
        ]];
    }

    private function resolveThumbnailUrl(CmsSlotEntity $slot, string $videoId): string
    {
        $data = $slot->getData();
        if ($data instanceof ImageStruct) {
            $url = $data->getMedia()?->getUrl();
            if (\is_string($url) && $url !== '') {
                return $url;
            }
        }

        // hqdefault.jpg existiert für JEDES YouTube-Video (480x360). maxresdefault.jpg gibt es nur bei
        // Videos mit HD-Thumbnail -> bei SD-/älteren Videos 404, und `thumbnailUrl` ist ein von Google
        // gefordertes VideoObject-Feld (tote URL = Rich-Result-Warnung).
        return 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
    }

    private function configString(CmsSlotEntity $slot, string $key): string
    {
        $value = $slot->getFieldConfig()->get($key)?->getValue();

        return \is_string($value) ? trim($value) : '';
    }
}
