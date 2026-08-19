<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;

/**
 * Liefert alle Slots einer aufgelösten CmsPage flach (Sections → Blocks → Slots).
 *
 * Gemeinsame Traversierung für alle CMS-block-basierten Provider (FAQ in 02b, Video in 02c) —
 * vermeidet doppelte Iterationslogik.
 */
final class CmsSlotIterator
{
    /**
     * @return list<CmsSlotEntity>
     */
    public function slots(?CmsPageEntity $page): array
    {
        if ($page === null) {
            return [];
        }

        $sections = $page->getSections();
        if ($sections === null) {
            return [];
        }

        $slots = [];
        foreach ($sections as $section) {
            $blocks = $section->getBlocks();
            if ($blocks === null) {
                continue;
            }

            foreach ($blocks as $block) {
                $blockSlots = $block->getSlots();
                if ($blockSlots === null) {
                    continue;
                }

                foreach ($blockSlots as $slot) {
                    $slots[] = $slot;
                }
            }
        }

        return $slots;
    }
}
