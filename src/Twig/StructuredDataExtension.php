<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Twig;

use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\Node\FaqQuestions;
use Ruhrcoder\RcStructuredData\Schema\OrganizationDetails;
use Ruhrcoder\RcStructuredData\Schema\Product\ProductOfferDetails;
use Ruhrcoder\RcStructuredData\Schema\Product\ShippingDetailsProvider;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\UrlEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Gibt den Vorlagen, was sie selbst nicht berechnen können: ob Shopware WebSite und Organization schon im
 * globalen Block ausgibt, Versand-, Preis- und Variantenangaben eines Angebots, die FAQ-Seite, die Firmenangaben des
 * Kanals und kodierte Medienadressen.
 *
 * Warum das nicht in Twig geht: `not feature('JSON_LD_DATA')` trägt nur, solange es das Flag
 * gibt. Mit Shopware 6.8 verschwindet es, und `Feature::isActive()` liefert für ein unbekanntes
 * Flag `false`. Die Bedingung wäre erfüllt, obwohl der Kern seinen globalen Block rendert.
 * WebSite und Organization stünden doppelt im `<head>`.
 *
 * Eine Versionsabfrage gibt es in Storefront-Vorlagen nicht, und jedes Flag, das man
 * ersatzweise heranzöge, verschwindet mit derselben Hauptversion. Beantwortbar ist die Frage
 * nur in PHP, über `Feature::has()`, das die Existenz des Flags von seinem Zustand trennt.
 */
final class StructuredDataExtension extends AbstractExtension
{
    private const JSON_LD_FLAG = 'JSON_LD_DATA';

    public function __construct(
        private readonly ShippingDetailsProvider $shippingDetails,
        private readonly FaqCmsBlockExtractor $faqExtractor,
        private readonly OrganizationDetails $organizationDetails,
        private readonly ProductOfferDetails $offerDetails,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rc_schema_core_renders_global_json_ld', $this->coreRendersGlobalJsonLd(...)),
            new TwigFunction('rc_schema_shipping_details', $this->shippingDetailsFor(...)),
            new TwigFunction('rc_schema_faq_page', $this->faqPage(...)),
            new TwigFunction('rc_schema_encode_media_urls', $this->encodeMediaUrls(...)),
            new TwigFunction('rc_schema_organization_details', $this->organizationDetails->forSalesChannel(...)),
            new TwigFunction('rc_schema_price_specification', $this->offerDetails->priceSpecification(...)),
            new TwigFunction('rc_schema_varies_by', $this->offerDetails->variesBy(...)),
        ];
    }

    /**
     * Die Versanddetails eines Produkts für `offers.shippingDetails`.
     *
     * Die Länder des Verkaufskanals stehen nicht am
     * Kontext, sondern in einer eigenen Zuordnung, und der vorberechnete Versandpreis liegt in
     * einem anderen Plugin. Beides in Twig zu holen hieße, in der Vorlage Datenbankabfragen zu
     * bauen, und der Preis wäre dort ohnehin nicht erreichbar (Shopware sperrt beim Rendern
     * die Felder, die die Berechnung braucht).
     *
     * @return list<array<string, mixed>>|null je Land mit eigenem Preis ein Eintrag
     */
    public function shippingDetailsFor(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        return $this->shippingDetails->forProduct($product, $context);
    }

    /**
     * Kodiert jede Medienadresse in einem JSON-LD-Block (`/media/…`, `/thumbnail/…`).
     *
     * Die Kernvorlagen übernehmen `media.url` roh; ein Dateiname mit Leerzeichen stünde sonst unkodiert im Markup, wie
     * vor dem Kodieren im HTML. Eine schon kodierte Adresse bleibt, wie sie ist.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function encodeMediaUrls(array $data): array
    {
        array_walk_recursive($data, static function (mixed &$value): void {
            // Eine Adresse mit kodierten Zeichen ist schon kodiert; ein zweites Mal machte aus %20 ein %2520.
            if (\is_string($value) && preg_match('~^https?://[^\s"]*/(?:media|thumbnail)/~', $value) === 1
                && preg_match('~%[0-9A-Fa-f]{2}~', $value) !== 1) {
                $value = UrlEncoder::encodeUrl($value) ?? $value;
            }
        });

        return $data;
    }

    /**
     * Eine FAQPage aus Fragen, die die Seite sichtbar zeigt, `null` ohne vollständiges Paar.
     *
     * Für die Produktseite: Das Theme zeigt dort Fragen aus dem FAQ-Baustein der Kategorie (`trKnowledge.faqs`). Sie
     * werden wie die des Bausteins gereinigt, damit Markup und sichtbarer Text übereinstimmen.
     *
     * @return array<string, mixed>|null
     */
    public function faqPage(mixed $items, string $pageUrl): ?array
    {
        $questions = FaqQuestions::fromItems($this->faqExtractor->items($items));
        if ($questions === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            '@id' => $pageUrl . '#faq',
            'mainEntity' => $questions,
        ];
    }

    /**
     * `true`, wenn der Kern WebSite und Organization selbst ausgibt; dann darf das Plugin
     * sie nicht noch einmal liefern.
     */
    public function coreRendersGlobalJsonLd(): bool
    {
        // Solange es das Flag gibt, hängt die Ausgabe des Kerns daran.
        if (Feature::has(self::JSON_LD_FLAG)) {
            return Feature::isActive(self::JSON_LD_FLAG);
        }

        // Ohne das Flag (Shopware 6.8) sind die strukturierten Daten fest eingebaut, und der Kern
        // gibt sie immer aus.
        return true;
    }
}
