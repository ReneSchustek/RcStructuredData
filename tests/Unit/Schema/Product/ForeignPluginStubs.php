<?php

declare(strict_types=1);

/*
 * Nachbildung der Berührungsfläche von RcProductFeedShippingExtension für den Testlauf.
 *
 * Das Plugin ist keine Abhängigkeit dieses Plugins (services.xml: `on-invalid="null"`) und liegt
 * beim Testlauf nicht im Ladepfad. Ohne diese Nachbildung ließe sich der Adapter gar nicht
 * prüfen. Nur die zwei Symbole, die er wirklich anfasst; alles weitere täuschte eine Kopplung
 * vor, die es nicht gibt.
 *
 * Die Abfrage auf `class_exists` ist kein Zierrat: Liegt das echte Plugin doch im Ladepfad,
 * gewinnt es, sonst stünden zwei Klassen desselben Namens im selben Prozess.
 */

namespace Ruhrcoder\RcProductFeedShippingExtension\Struct;

if (!class_exists(ShippingCalculationResult::class, false)) {
    class ShippingCalculationResult
    {
        public function __construct(
            public readonly string $productId,
            public readonly string $countryIso,
            public readonly float $shippingCost,
            public readonly string $currencyIso,
            public readonly bool $isFallback,
        ) {
        }
    }
}

namespace Ruhrcoder\RcProductFeedShippingExtension\Service;

use Ruhrcoder\RcProductFeedShippingExtension\Struct\ShippingCalculationResult;

if (!class_exists(ShippingCostCalculatorService::class, false)) {
    class ShippingCostCalculatorService
    {
        public function lookupCached(string $productId, string $countryIso, string $salesChannelId): ?ShippingCalculationResult
        {
            return null;
        }
    }
}
