<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Product;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Product\PrecalculatedShippingRate;
use Ruhrcoder\RcStructuredData\Schema\Product\ShippingDetailsProvider;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Die drei Felder, die Google in `offers.shippingDetails` vermisst.
 *
 * `handlingTime` ist die Zeit im Haus (steht am
 * Produkt), `transitTime` die Zeit auf der Straße (steht an der Versandart). Beide aus derselben
 * Quelle zu nehmen wäre für den Kunden eine doppelt gezählte Woche, deshalb hat jeder der beiden
 * Werte hier seinen eigenen Test.
 */
final class ShippingDetailsProviderTest extends TestCase
{
    public function testItNamesTheCountriesOfTheSalesChannel(): void
    {
        $details = $this->provider(['DE', 'AT'])->forProduct($this->product(), $this->context());

        self::assertNotNull($details);
        self::assertCount(1, $details);
        self::assertSame([
            ['@type' => 'DefinedRegion', 'addressCountry' => 'AT'],
            ['@type' => 'DefinedRegion', 'addressCountry' => 'DE'],
        ], $details[0]['shippingDestination']);
    }

    /**
     * Was: Bearbeitungszeit vom Produkt, Laufzeit von der Versandart.
     * Warum: Shopware gibt nur die Bearbeitungszeit aus; ohne `transitTime` meldet
     *        Google ein fehlendes Pflichtfeld.
     */
    public function testItSeparatesHandlingTimeFromTransitTime(): void
    {
        $details = $this->provider(['DE'])->forProduct(
            $this->product($this->deliveryTime(13, 15)),
            $this->context($this->deliveryTime(2, 4)),
        );

        self::assertNotNull($details);
        self::assertSame(13, $details[0]['deliveryTime']['handlingTime']['minValue']);
        self::assertSame(15, $details[0]['deliveryTime']['handlingTime']['maxValue']);
        self::assertSame(2, $details[0]['deliveryTime']['transitTime']['minValue']);
        self::assertSame(4, $details[0]['deliveryTime']['transitTime']['maxValue']);
    }

    /**
     * Was: Ein Produkt ohne Lieferzeit.
     * Warum: Ohne Lieferzeit am Produkt lässt Shopware `shippingDetails` ganz weg. Die Laufzeit
     *        und die Länder stehen trotzdem zur Verfügung, die Angabe bleibt also nicht leer.
     */
    public function testAProductWithoutADeliveryTimeStillGetsTransitTime(): void
    {
        $details = $this->provider(['DE'])->forProduct($this->product(), $this->context($this->deliveryTime(2, 4)));

        self::assertNotNull($details);
        self::assertArrayNotHasKey('handlingTime', $details[0]['deliveryTime']);
        self::assertSame(2, $details[0]['deliveryTime']['transitTime']['minValue']);
    }

    public function testItCarriesThePrecalculatedRate(): void
    {
        $details = $this->provider(['DE'], ['amount' => 8.93, 'currency' => 'EUR'])
            ->forProduct($this->product(), $this->context());

        self::assertNotNull($details);
        self::assertSame([
            '@type' => 'MonetaryAmount',
            'value' => 8.93,
            'currency' => 'EUR',
        ], $details[0]['shippingRate']);
    }

    /**
     * Was: Ohne den Warenstrom.
     * Warum: Das Plugin läuft ohne den Warenstrom unverändert weiter, nur ohne
     *        Preis. Ein `shippingRate` von 0,00 € wäre eine Zusage, die der Shop nicht hält.
     */
    public function testWithoutAPrecalculatedRateTheFieldStaysAway(): void
    {
        $details = $this->provider(['DE'])->forProduct($this->product(), $this->context());

        self::assertNotNull($details);
        self::assertArrayNotHasKey('shippingRate', $details[0]);
    }

    /**
     * Was: Drei Lieferländer, Preise für Deutschland und Österreich, keiner für die Schweiz.
     * Warum: Google liest einen Preis als Preis für alle Ziele desselben Eintrags. Ein deutscher Paketpreis für
     *        alle drei Länder wäre für die Schweiz falsch.
     * Erwartet: je Land mit Preis ein eigener Eintrag, die Schweiz in einem Eintrag ohne Preis.
     */
    public function testEachCountryWithARateGetsItsOwnEntry(): void
    {
        $rates = ['DE' => ['amount' => 8.93, 'currency' => 'EUR'], 'AT' => ['amount' => 19.9, 'currency' => 'EUR']];
        $details = $this->provider(['DE', 'AT', 'CH'], null, $rates)->forProduct($this->product(), $this->context());

        self::assertNotNull($details);
        self::assertCount(3, $details);
        self::assertSame([['@type' => 'DefinedRegion', 'addressCountry' => 'AT']], $details[0]['shippingDestination']);
        self::assertSame(19.9, $details[0]['shippingRate']['value']);
        self::assertSame([['@type' => 'DefinedRegion', 'addressCountry' => 'DE']], $details[1]['shippingDestination']);
        self::assertSame(8.93, $details[1]['shippingRate']['value']);
        self::assertSame([['@type' => 'DefinedRegion', 'addressCountry' => 'CH']], $details[2]['shippingDestination']);
        self::assertArrayNotHasKey('shippingRate', $details[2]);
    }

    /**
     * Was: Keine einzige Angabe kommt zustande.
     * Warum: Dann darf kein leeres `OfferShippingDetails` entstehen: Ein Objekt, das nur seinen
     *        eigenen Typ nennt, ist für Google ein unvollständiges Feld statt eines fehlenden.
     */
    public function testWithoutAnySourceThereAreNoShippingDetails(): void
    {
        self::assertNull($this->provider([])->forProduct($this->product(), $this->context()));
    }

    /**
     * @param list<string>                          $countryIsoCodes
     * @param array{amount: float, currency: string}|null $rate
     * @param array<string, array{amount: float, currency: string}> $ratesByCountry
     */
    private function provider(array $countryIsoCodes, ?array $rate = null, array $ratesByCountry = []): ShippingDetailsProvider
    {
        $countries = [];
        foreach ($countryIsoCodes as $iso) {
            $country = new CountryEntity();
            $country->setId(str_pad($iso, 32, '0'));
            $country->setUniqueIdentifier(str_pad($iso, 32, '0'));
            $country->setIso($iso);
            $countries[] = $country;
        }

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'country',
            \count($countries),
            new CountryCollection($countries),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        // Der Nachschlag der Laufzeit: Die Tests setzen sie direkt an die Versandart im Kontext,
        // deshalb bleibt dieses Ergebnis leer.
        $shippingMethods = $this->createMock(EntityRepository::class);
        $shippingMethods->method('search')->willReturn(new EntitySearchResult(
            'shipping_method',
            0,
            new ShippingMethodCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $shippingRate = new class ($rate, $ratesByCountry) implements PrecalculatedShippingRate {
            /**
             * @param array{amount: float, currency: string}|null           $rate
             * @param array<string, array{amount: float, currency: string}> $ratesByCountry
             */
            public function __construct(private readonly ?array $rate, private readonly array $ratesByCountry)
            {
            }

            public function rateFor(string $productId, string $countryIso, string $salesChannelId): ?array
            {
                return $this->ratesByCountry[$countryIso] ?? $this->rate;
            }
        };

        return new ShippingDetailsProvider($repository, $shippingMethods, $shippingRate);
    }

    private function product(?DeliveryTimeEntity $deliveryTime = null): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId('0af8f0b5c9d34c8fa1b2c3d4e5f60718');
        $product->setUniqueIdentifier('0af8f0b5c9d34c8fa1b2c3d4e5f60718');

        if ($deliveryTime !== null) {
            $product->setDeliveryTime($deliveryTime);
        }

        return $product;
    }

    private function context(?DeliveryTimeEntity $transit = null): SalesChannelContext
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('sm-1');
        $shippingMethod->setUniqueIdentifier('sm-1');
        if ($transit !== null) {
            $shippingMethod->setDeliveryTime($transit);
        }

        $country = new CountryEntity();
        $country->setId(str_pad('DE', 32, '0'));
        $country->setUniqueIdentifier(str_pad('DE', 32, '0'));
        $country->setIso('DE');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('kanal-1');
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));

        return $context;
    }

    private function deliveryTime(int $min, int $max): DeliveryTimeEntity
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId('dt-' . $min . '-' . $max);
        $deliveryTime->setUniqueIdentifier('dt-' . $min . '-' . $max);
        $deliveryTime->setMin($min);
        $deliveryTime->setMax($max);
        $deliveryTime->setUnit('day');

        return $deliveryTime;
    }
}
