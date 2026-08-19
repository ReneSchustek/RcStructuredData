<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Baut CMS-Slots und CmsPages für Unit-Tests ohne Kernel-Boot.
 */
trait CmsFixtureTrait
{
    private int $fixtureCounter = 0;

    /**
     * @param list<array{question?: string, answer?: string}>|null $faqItems
     */
    private function makeSlot(string $type, ?array $faqItems = null, ?string $displayMode = null): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $id = 'slot-' . ++$this->fixtureCounter;
        $slot->setUniqueIdentifier($id);
        $slot->setSlot($id);
        $slot->setType($type);

        $fieldConfig = new FieldConfigCollection();
        if ($faqItems !== null) {
            $fieldConfig->add(new FieldConfig('faqItems', FieldConfig::SOURCE_STATIC, $faqItems));
        }
        if ($displayMode !== null) {
            $fieldConfig->add(new FieldConfig('displayMode', FieldConfig::SOURCE_STATIC, $displayMode));
        }
        $slot->setFieldConfig($fieldConfig);

        return $slot;
    }

    /**
     * @param array<string, string> $config Zusätzliche fieldConfig-Werte (z. B. rcSchemaName)
     */
    private function makeYoutubeSlot(?string $videoId, array $config = [], ?string $thumbnailUrl = null): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $id = 'slot-' . ++$this->fixtureCounter;
        $slot->setUniqueIdentifier($id);
        $slot->setSlot($id);
        $slot->setType('youtube-video');

        $fieldConfig = new FieldConfigCollection();
        if ($videoId !== null) {
            $fieldConfig->add(new FieldConfig('videoID', FieldConfig::SOURCE_STATIC, $videoId));
        }
        foreach ($config as $key => $value) {
            $fieldConfig->add(new FieldConfig($key, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($fieldConfig);

        if ($thumbnailUrl !== null) {
            $media = new MediaEntity();
            $media->setUrl($thumbnailUrl);
            $image = new ImageStruct();
            $image->setMedia($media);
            $slot->setData($image);
        }

        return $slot;
    }

    /**
     * Baut einen product-listing-Slot mit aufgelöster Listing-Data.
     *
     * $productIds === null → gar keine Data (nicht aufgelöst); [] → leere Liste.
     *
     * @param list<string>|null $productIds
     */
    private function makeProductListingSlot(?array $productIds): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $id = 'slot-' . ++$this->fixtureCounter;
        $slot->setUniqueIdentifier($id);
        $slot->setSlot($id);
        $slot->setType('product-listing');
        $slot->setFieldConfig(new FieldConfigCollection());

        if ($productIds !== null) {
            $products = [];
            foreach ($productIds as $productId) {
                $product = new SalesChannelProductEntity();
                $product->setId($productId);
                $product->setUniqueIdentifier($productId);
                $products[] = $product;
            }

            // `setListing()` gibt den Typparameter vor; ohne diese Angabe leitet die
            // statische Prüfung ihn aus der eingesetzten Entität ab und meldet ihn als zu eng.
            /** @var EntitySearchResult<ProductCollection> $listing */
            $listing = new EntitySearchResult(
                'product',
                \count($products),
                new ProductCollection($products),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            );

            $struct = new ProductListingStruct();
            $struct->setListing($listing);
            $slot->setData($struct);
        }

        return $slot;
    }

    private function makeCmsPage(CmsSlotEntity ...$slots): CmsPageEntity
    {
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier('block-' . ++$this->fixtureCounter);
        $block->setSlots(new CmsSlotCollection($slots));

        $section = new CmsSectionEntity();
        $section->setUniqueIdentifier('section-' . ++$this->fixtureCounter);
        $section->setBlocks(new CmsBlockCollection([$block]));

        $page = new CmsPageEntity();
        $page->setUniqueIdentifier('page-' . ++$this->fixtureCounter);
        $page->setSections(new CmsSectionCollection([$section]));

        return $page;
    }
}
