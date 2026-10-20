<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Product\ProductOfferDetails;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Preisangabe und `variesBy`, die der Kern am Produkt-Markup auslässt.
 */
#[CoversClass(ProductOfferDetails::class)]
final class ProductOfferDetailsTest extends TestCase
{
    /**
     * Was: Ein Preis ohne Staffel, Bruttoanzeige.
     * Erwartet: eine UnitPriceSpecification mit Preis, Währung und enthaltener Mehrwertsteuer, ohne Menge.
     */
    public function testASinglePriceIsOneSpecification(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setCalculatedPrices(new PriceCollection());
        $product->setCalculatedPrice(self::price(9.664, 1));

        self::assertSame([[
            '@type' => 'UnitPriceSpecification',
            'price' => 9.66,
            'priceCurrency' => 'EUR',
            'valueAddedTaxIncluded' => true,
        ]], (new ProductOfferDetails())->priceSpecification($product, $this->context(CartPrice::TAX_STATE_GROSS)));
    }

    /**
     * Was: Drei Staffeln (bis 9, bis 49, ab 50), Nettoanzeige.
     * Warum: Der Kern führt je Staffel die Obergrenze, bei der letzten die Untergrenze; daraus folgen die Mengen.
     * Erwartet: 1–9, 10–49, ab 50; Mehrwertsteuer nicht enthalten.
     */
    public function testTiersCarryTheirQuantities(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setCalculatedPrices(
            new PriceCollection([
                self::price(10.0, 9),
                self::price(9.0, 49),
                self::price(8.0, 50),
            ])
        );

        $specification = (new ProductOfferDetails())
            ->priceSpecification(
                $product,
                $this->context(CartPrice::TAX_STATE_NET)
            );

        self::assertFalse($specification[0]['valueAddedTaxIncluded']);
        self::assertSame(
            [
                ['@type' => 'QuantitativeValue', 'minValue' => 1, 'unitCode' => 'C62', 'maxValue' => 9],
                ['@type' => 'QuantitativeValue', 'minValue' => 10, 'unitCode' => 'C62', 'maxValue' => 49],
                ['@type' => 'QuantitativeValue', 'minValue' => 50, 'unitCode' => 'C62'],
            ],
            array_column($specification, 'eligibleQuantity'),
        );
        self::assertSame([10.0, 9.0, 8.0], array_column($specification, 'price'));
    }

    /**
     * Was: Varianten nach Länge, Material, Ausführung und Maße.
     * Warum: Google kennt für `variesBy` nur wenige Werte; ein unbekannter wäre ein Fehler.
     * Erwartet: size (einmal) und material; Ausführung fällt weg.
     */
    public function testVariesByKeepsOnlyKnownProperties(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setVariation([
            ['group' => 'Länge', 'option' => '3 m'],
            ['group' => 'Material', 'option' => 'V2A'],
            ['group' => 'Ausführung', 'option' => 'gebürstet'],
            ['group' => ' Maße ', 'option' => '40 x 40'],
        ]);

        self::assertSame(
            ['https://schema.org/size', 'https://schema.org/material'],
            (new ProductOfferDetails())->variesBy($product),
        );
    }

    private static function price(float $unitPrice, int $quantity): CalculatedPrice
    {
        return new CalculatedPrice(
            $unitPrice,
            $unitPrice * $quantity,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $quantity
        );
    }

    private function context(string $taxState): SalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getTaxState')->willReturn($taxState);

        return $context;
    }
}
