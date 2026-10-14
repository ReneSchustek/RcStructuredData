<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Product;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Baut `offers.shippingDetails` für die Produktseite.
 *
 * **Der Anlass:** Die Search Console meldete drei fehlende Felder — `shippingRate`,
 * `shippingDestination` und `transitTime`. Shopware selbst gibt nur die Bearbeitungszeit aus
 * (`handlingTime` aus der Lieferzeit des Produkts); alles andere kennt die Kernvorlage nicht.
 *
 * **Die Trennung, um die es fachlich geht:** `handlingTime` ist die Zeit im Haus, bis die Ware
 * das Lager verlässt — sie steht am Produkt. `transitTime` ist die Zeit auf der Straße; sie
 * steht an der Versandart. Beide in dasselbe Feld zu schreiben, wäre für den Kunden eine
 * doppelt gezählte Woche.
 */
// Bewusst nicht `final`: Die Twig-Erweiterung wird in ihren Tests gegen ein Test-Double dieses
// Anbieters geprüft — dort geht es um die Flag-Frage, nicht um die Versanddetails.
class ShippingDetailsProvider
{
    /** @var array<string, list<string>> Länder je Verkaufskanal, einmal je Aufruf gelesen */
    private array $countryCache = [];

    /** @var array<string, DeliveryTimeEntity|null> Laufzeit je Versandart, einmal je Aufruf gelesen */
    private array $transitCache = [];

    /**
     * @param EntityRepository<CountryCollection>        $countryRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly PrecalculatedShippingRate $shippingRate,
    ) {
    }

    /**
     * @return array<string, mixed>|null `null`, wenn nicht eine einzige Angabe zustande kommt
     */
    public function forProduct(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $details = ['@type' => 'OfferShippingDetails'];

        $destinations = $this->destinations($context);
        if ($destinations !== []) {
            $details['shippingDestination'] = $destinations;
        }

        $rate = $this->rate($product, $context);
        if ($rate !== null) {
            $details['shippingRate'] = $rate;
        }

        $deliveryTime = $this->deliveryTime($product, $context);
        if ($deliveryTime !== null) {
            $details['deliveryTime'] = $deliveryTime;
        }

        return \count($details) > 1 ? $details : null;
    }

    /**
     * Die Länder des Verkaufskanals als `DefinedRegion`.
     *
     * Nicht das Land des Besuchers: Die strukturierten Daten beschreiben das Angebot, nicht den
     * einen Aufruf. Google liest daraus, wohin überhaupt geliefert wird.
     *
     * @return list<array<string, string>>
     */
    private function destinations(SalesChannelContext $context): array
    {
        $regions = [];

        foreach ($this->countryIsoCodes($context) as $iso) {
            $regions[] = ['@type' => 'DefinedRegion', 'addressCountry' => $iso];
        }

        return $regions;
    }

    /**
     * @return list<string>
     */
    private function countryIsoCodes(SalesChannelContext $context): array
    {
        $salesChannelId = $context->getSalesChannelId();

        if (isset($this->countryCache[$salesChannelId])) {
            return $this->countryCache[$salesChannelId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('shippingAvailable', true));

        $countries = $this->countryRepository->search($criteria, $context->getContext())->getEntities();

        $isoCodes = [];
        foreach ($countries as $country) {
            $iso = $country->getIso();
            if (\is_string($iso) && $iso !== '') {
                $isoCodes[] = $iso;
            }
        }

        sort($isoCodes);

        return $this->countryCache[$salesChannelId] = $isoCodes;
    }

    /**
     * Bearbeitungszeit aus dem Produkt, Laufzeit aus der Versandart.
     *
     * @return array<string, mixed>|null
     */
    private function deliveryTime(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $deliveryTime = ['@type' => 'ShippingDeliveryTime'];

        $handling = $product->getDeliveryTime();
        if ($handling !== null) {
            $deliveryTime['handlingTime'] = [
                '@type' => 'QuantitativeValue',
                'minValue' => $handling->getMin(),
                'maxValue' => $handling->getMax(),
                'unitCode' => 'DAY',
            ];
        }

        $transit = $this->transitTime($context);
        if ($transit !== null) {
            $deliveryTime['transitTime'] = [
                '@type' => 'QuantitativeValue',
                'minValue' => $transit->getMin(),
                'maxValue' => $transit->getMax(),
                'unitCode' => 'DAY',
            ];
        }

        return \count($deliveryTime) > 1 ? $deliveryTime : null;
    }

    /**
     * Die Laufzeit der Versandart — notfalls nachgeladen.
     *
     * **Warum nachladen und nicht einfach am Kontext ablesen:** Shopware baut den Kontext mit den
     * Verknüpfungen, die der Bestellvorgang braucht; die Lieferzeit der Versandart gehört nicht
     * dazu. Am 2026-08-24 auf einer Kopie des Shops gemessen: `getDeliveryTime()` liefert `null`, obwohl an
     * der Versandart eine Lieferzeit hängt. Ohne diesen Nachschlag bliebe `transitTime` genau das
     * Feld, das Google vermisst.
     */
    private function transitTime(SalesChannelContext $context): ?DeliveryTimeEntity
    {
        $shippingMethod = $context->getShippingMethod();

        $transit = $shippingMethod->getDeliveryTime();
        if ($transit !== null) {
            return $transit;
        }

        $id = $shippingMethod->getId();
        if (\array_key_exists($id, $this->transitCache)) {
            return $this->transitCache[$id];
        }

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('deliveryTime');

        $loaded = $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities()->first();

        return $this->transitCache[$id] = $loaded?->getDeliveryTime();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rate(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $iso = $context->getShippingLocation()->getCountry()->getIso();
        if (!\is_string($iso) || $iso === '') {
            return null;
        }

        $rate = $this->shippingRate->rateFor($product->getId(), $iso, $context->getSalesChannelId());
        if ($rate === null) {
            return null;
        }

        return [
            '@type' => 'MonetaryAmount',
            'value' => $rate['amount'],
            'currency' => $rate['currency'],
        ];
    }
}
