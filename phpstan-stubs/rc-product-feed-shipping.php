<?php

// Symbol-Definitionen für die optionale Anbindung an RcProductFeedShippingExtension.
// Nur für die statische Analyse (phpstan scanFiles) — zur Laufzeit kommt der Dienst aus dem
// installierten Warenstrom-Plugin, und ohne dieses bleibt die Abhängigkeit null
// (services.xml: on-invalid="null").

namespace Ruhrcoder\RcProductFeedShippingExtension\Service {
    class ShippingCostCalculatorService
    {
        public function lookupCached(
            string $productId,
            string $countryIso,
            string $salesChannelId,
        ): ?\Ruhrcoder\RcProductFeedShippingExtension\Struct\ShippingCalculationResult {
        }
    }
}

namespace Ruhrcoder\RcProductFeedShippingExtension\Struct {
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
