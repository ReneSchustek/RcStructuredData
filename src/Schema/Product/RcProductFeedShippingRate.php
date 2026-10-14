<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Product;

use Ruhrcoder\RcProductFeedShippingExtension\Service\ShippingCostCalculatorService;
use Throwable;

/**
 * Liest den vorberechneten Versandpreis aus `RcProductFeedShippingExtension`.
 *
 * Der Dienst ist optional (`services.xml`: `on-invalid="null"`): Fehlt der Warenstrom, ist
 * `$calculator` null, und die strukturierten Daten kommen ohne `shippingRate` aus. Der
 * nullable Typ-Hint verlangt bei null keinen Autoload — die fremde Klasse wird dann nie
 * geladen. Dasselbe Muster wie beim A/B-Schalter in RcCheckoutEnhancer.
 *
 * **Ausdrücklich nur der Zwischenspeicher.** `lookupCached()` schlägt nach, rechnet aber
 * nicht: Die Berechnung braucht die Warenkorb-Regelauswertung und läuft während des Renderns
 * einer Vorlage grundsätzlich in eine Ausnahme (Shopware sperrt dort `RuleEntity::payload`).
 * Wer hier rechnen wollte, bekäme auf jeder Produktseite eine Ausnahme und im besten Fall
 * einen Ersatzwert.
 */
final class RcProductFeedShippingRate implements PrecalculatedShippingRate
{
    public function __construct(
        private readonly ?ShippingCostCalculatorService $calculator = null,
    ) {
    }

    public function rateFor(string $productId, string $countryIso, string $salesChannelId): ?array
    {
        if ($this->calculator === null) {
            return null;
        }

        try {
            $result = $this->calculator->lookupCached($productId, $countryIso, $salesChannelId);
        } catch (Throwable) {
            // Fail-Soft: Die strukturierten Daten sind ein Zusatz. Ein Fehler im anderen Plugin
            // darf die Produktseite nicht mit einem 500 abreißen — dann fehlt eben der Preis.
            return null;
        }

        if ($result === null) {
            return null;
        }

        // Ein Ersatzwert ist geraten und gehört nicht in eine Angabe an Google: Er stammt nicht
        // aus der Berechnung für dieses Produkt, sondern aus der Voreinstellung des Kanals.
        if ($result->isFallback) {
            return null;
        }

        return ['amount' => $result->shippingCost, 'currency' => $result->currencyIso];
    }
}
