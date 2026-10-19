<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Product;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Was der Kern am Produkt-Markup auslässt: die Preisangabe als `UnitPriceSpecification` (mit Staffeln und dem Hinweis,
 * ob die Mehrwertsteuer enthalten ist) und `variesBy` einer Produktgruppe.
 */
final class ProductOfferDetails
{
    /**
     * Eigenschaftsgruppen, die einem Wert entsprechen, den Google für `variesBy` kennt. Andere Gruppen (Ausführung,
     * Anschluss) haben keinen Gegenwert und bleiben weg; ein unbekannter Wert wäre ein Fehler, kein Hinweis.
     */
    private const VARIES_BY = [
        'größe' => 'size', 'size' => 'size', 'maße' => 'size', 'länge' => 'size', 'length' => 'size', 'höhe' => 'size',
        'height' => 'size', 'breite' => 'size', 'width' => 'size', 'tiefe' => 'size', 'durchmesser' => 'size',
        'außendurchmesser' => 'size', 'glasstärke' => 'size', 'stärke' => 'size',
        'material' => 'material', 'farbe' => 'color', 'color' => 'color', 'colour' => 'color',
        'muster' => 'pattern', 'pattern' => 'pattern',
    ];

    /**
     * Je Preisstufe eine `UnitPriceSpecification`; bei einer Stufe ohne Mengenangabe.
     *
     * @return list<array<string, mixed>>
     */
    public function priceSpecification(SalesChannelProductEntity $product, SalesChannelContext $context): array
    {
        $tiers = array_values($product->getCalculatedPrices()->getElements());
        if ($tiers === []) {
            $tiers = [$product->getCalculatedPrice()];
        }

        $currency = $context->getCurrency();
        $decimals = $currency->getItemRounding()->getDecimals();
        $taxIncluded = $context->getTaxState() === CartPrice::TAX_STATE_GROSS;

        $specifications = [];
        foreach ($tiers as $index => $tier) {
            $specification = [
                '@type' => 'UnitPriceSpecification',
                'price' => round($tier->getUnitPrice(), $decimals),
                'priceCurrency' => $currency->getIsoCode(),
                'valueAddedTaxIncluded' => $taxIncluded,
            ];

            if (\count($tiers) > 1) {
                $specification['eligibleQuantity'] = self::quantity($tiers[$index - 1] ?? null, $tier, isset($tiers[$index + 1]));
            }

            $specifications[] = $specification;
        }

        return $specifications;
    }

    /**
     * Die Eigenschaften, in denen sich die Varianten unterscheiden, als Werte von Schema.org.
     *
     * @return list<string>
     */
    public function variesBy(SalesChannelProductEntity $product): array
    {
        $values = [];
        foreach ($product->getVariation() as $variation) {
            $group = mb_strtolower(trim((string) ($variation['group'] ?? '')));
            if (isset(self::VARIES_BY[$group])) {
                $values[] = 'https://schema.org/' . self::VARIES_BY[$group];
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * Die Mengen einer Stufe. Der Kern führt je Stufe ihre Obergrenze, bei der letzten ihre Untergrenze; die Untergrenze
     * folgt deshalb aus der Stufe davor.
     *
     * @return array<string, mixed>
     */
    private static function quantity(?CalculatedPrice $previous, CalculatedPrice $tier, bool $hasNext): array
    {
        $quantity = ['@type' => 'QuantitativeValue', 'minValue' => $previous === null ? 1 : $previous->getQuantity() + 1, 'unitCode' => 'C62'];
        if ($hasNext) {
            $quantity['maxValue'] = $tier->getQuantity();
        }

        return $quantity;
    }
}
