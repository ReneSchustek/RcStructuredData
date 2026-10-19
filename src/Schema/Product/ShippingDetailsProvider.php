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
 * Ohne diesen Anbieter meldet die Search Console drei fehlende Felder: `shippingRate`,
 * `shippingDestination` und `transitTime`. Shopware selbst gibt nur die Bearbeitungszeit aus
 * (`handlingTime` aus der Lieferzeit des Produkts); alles andere kennt die Kernvorlage nicht.
 *
 * `handlingTime` ist die Zeit im Haus, bis die Ware
 * das Lager verlässt (sie steht am Produkt). `transitTime` ist die Zeit auf der Straße; sie
 * steht an der Versandart. Beide in dasselbe Feld zu schreiben, wäre für den Kunden eine
 * doppelt gezählte Woche.
 *
 * **Ein Eintrag je Land mit eigenem Preis.** Google liest `shippingRate` als Preis für genau die Ziele desselben
 * `OfferShippingDetails` („Information about the cost of shipping to the specified destination", höchstens ein Preis je
 * Eintrag, für mehrere Preise mehrere Einträge; Merchant-Listing-Dokumentation, gelesen 2026-10-19). Stünden alle
 * Lieferländer mit dem Preis des Besucherlands in einem Eintrag, gälte der deutsche Paketpreis auch für die Schweiz.
 * Länder ohne vorberechneten Preis stehen gesammelt in einem Eintrag ohne `shippingRate`: Google erfährt, dass dorthin
 * geliefert wird, ohne dass ein Preis behauptet wird.
 */
// Bewusst nicht `final`: Die Twig-Erweiterung wird in ihren Tests gegen ein Test-Double dieses
// Anbieters geprüft. Dort geht es um die Flag-Frage, nicht um die Versanddetails.
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
     * Die Einträge für `offers.shippingDetails`: einer je Land mit Preis, dazu einer für die Länder ohne Preis.
     *
     * Die Länder sind die des Verkaufskanals, nicht das Land des Besuchers: Die strukturierten Daten beschreiben das
     * Angebot, nicht den einen Aufruf.
     *
     * @return list<array<string, mixed>>|null `null`, wenn nicht eine einzige Angabe zustande kommt
     */
    public function forProduct(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $deliveryTime = $this->deliveryTime($product, $context);
        $entries = [];
        $withoutRate = [];

        foreach ($this->countryIsoCodes($context) as $iso) {
            $rate = $this->rate($product, $iso, $context);
            if ($rate === null) {
                $withoutRate[] = $iso;

                continue;
            }

            $entries[] = $this->entry([$iso], $rate, $deliveryTime);
        }

        if ($withoutRate !== [] || ($entries === [] && $deliveryTime !== null)) {
            $entries[] = $this->entry($withoutRate, null, $deliveryTime);
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * @param list<string>              $isoCodes
     * @param array<string, mixed>|null $rate
     * @param array<string, mixed>|null $deliveryTime
     *
     * @return array<string, mixed>
     */
    private function entry(array $isoCodes, ?array $rate, ?array $deliveryTime): array
    {
        $entry = ['@type' => 'OfferShippingDetails'];

        if ($isoCodes !== []) {
            $entry['shippingDestination'] = array_map(
                static fn (string $iso): array => ['@type' => 'DefinedRegion', 'addressCountry' => $iso],
                $isoCodes,
            );
        }

        if ($rate !== null) {
            $entry['shippingRate'] = $rate;
        }

        if ($deliveryTime !== null) {
            $entry['deliveryTime'] = $deliveryTime;
        }

        return $entry;
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
     * Die Laufzeit der Versandart, notfalls nachgeladen.
     *
     * Am Kontext lässt sie sich nicht einfach ablesen: Shopware baut den Kontext mit den
     * Verknüpfungen, die der Bestellvorgang braucht, und die Lieferzeit der Versandart gehört nicht
     * dazu. `getDeliveryTime()` liefert dann `null`, obwohl an der Versandart eine Lieferzeit hängt. Ohne diesen Nachschlag bliebe `transitTime` genau das
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
    private function rate(SalesChannelProductEntity $product, string $iso, SalesChannelContext $context): ?array
    {
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
