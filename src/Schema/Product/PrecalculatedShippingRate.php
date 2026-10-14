<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Product;

/**
 * Woher der Versandpreis für `offers.shippingDetails.shippingRate` kommt.
 *
 * **Warum eine eigene Schnittstelle und nicht der Dienst des anderen Plugins:** Die Zahl liegt
 * in `RcProductFeedShippingExtension`, das sie für den Warenstrom ohnehin je Produkt und Land
 * vorberechnet. Dieses Plugin darf davon aber nicht abhängen — es muss ohne den Warenstrom
 * unverändert laufen. Die Schnittstelle trennt die Frage („was kostet der Versand für dieses
 * Produkt nach dort?") von der einen Antwort, die es heute darauf gibt.
 */
interface PrecalculatedShippingRate
{
    /**
     * Der vorberechnete Versandpreis — oder `null`, wenn keiner vorliegt.
     *
     * `null` ist der Normalfall ohne den Warenstrom und darf niemals als „kostenlos"
     * durchgehen: Ein `shippingRate` von 0,00 € wäre eine Zusage, die der Shop nicht hält.
     *
     * @return array{amount: float, currency: string}|null
     */
    public function rateFor(string $productId, string $countryIso, string $salesChannelId): ?array;
}
