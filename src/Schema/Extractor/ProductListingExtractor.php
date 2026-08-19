<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;

/**
 * Liest die Produkt-IDs der auf der Seite gelisteten Produkte aus einem product-listing-Slot.
 *
 * Quelle ist die aufgelöste Slot-Data (`ProductListingStruct::getListing()`) — also genau die
 * paginierte Scheibe, die der Besucher sieht. Damit bleibt der ItemList konsistent zum sichtbaren
 * Listing, analog zur „eine Parsing-Stelle"-Regel der übrigen Extraktoren.
 */
final class ProductListingExtractor
{
    private const ELEMENT_TYPE = 'product-listing';

    public function supports(CmsSlotEntity $slot): bool
    {
        return $slot->getType() === self::ELEMENT_TYPE;
    }

    /**
     * @return list<string> Produkt-IDs in Listenreihenfolge
     */
    public function extract(CmsSlotEntity $slot): array
    {
        $data = $slot->getData();
        if (!$data instanceof ProductListingStruct) {
            return [];
        }

        $listing = $data->getListing();
        if ($listing === null) {
            return [];
        }

        $ids = [];
        foreach ($listing as $product) {
            $id = $product->getId();
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
