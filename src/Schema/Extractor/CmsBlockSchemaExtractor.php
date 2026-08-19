<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;

/**
 * Vertrag für das Extrahieren von Schema-Rohdaten aus einem CMS-Slot.
 *
 * Pro CMS-Element-Typ (rc-faq, youtube-video, ...) eine Implementierung. So bleibt der
 * sichtbare CMS-Block die einzige Datenquelle für das zugehörige Schema (DRY, Google-konform:
 * Schema == sichtbarer Inhalt). Neue Block-Typen = neuer Extractor (OCP).
 */
interface CmsBlockSchemaExtractor
{
    public function supports(CmsSlotEntity $slot): bool;

    /**
     * @return list<array<string, mixed>> Rohdaten (Form je Extractor)
     */
    public function extract(CmsSlotEntity $slot): array;
}
