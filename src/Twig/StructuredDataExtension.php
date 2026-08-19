<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Twig;

use Shopware\Core\Framework\Feature;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Beantwortet der Vorlage die eine Frage, die sie selbst nicht beantworten kann:
 * Gibt Shopware WebSite und Organization bereits im globalen Block aus?
 *
 * Warum das nicht in Twig geht: Die Vorlage prüfte bisher `not feature('JSON_LD_DATA')`.
 * Das trug, solange es das Flag gibt. Mit Shopware 6.8 verschwindet es, und
 * `Feature::isActive()` liefert für ein unbekanntes Flag `false` — die Bedingung wäre also
 * erfüllt, obwohl der Kern seinen globalen Block dort sehr wohl rendert. WebSite und
 * Organization stünden doppelt im `<head>`.
 *
 * Eine Versionsabfrage gibt es in Storefront-Vorlagen nicht, und jedes Flag, das man
 * ersatzweise heranzöge, verschwindet mit derselben Hauptversion. Beantwortbar ist die Frage
 * nur in PHP — über `Feature::has()`, das die Existenz des Flags von seinem Zustand trennt.
 */
class StructuredDataExtension extends AbstractExtension
{
    private const JSON_LD_FLAG = 'JSON_LD_DATA';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rc_schema_core_renders_global_json_ld', $this->coreRendersGlobalJsonLd(...)),
        ];
    }

    /**
     * `true`, wenn der Kern WebSite und Organization selbst ausgibt — dann darf das Plugin
     * sie nicht noch einmal liefern.
     */
    public function coreRendersGlobalJsonLd(): bool
    {
        // Solange es das Flag gibt, hängt die Ausgabe des Kerns daran.
        if (Feature::has(self::JSON_LD_FLAG)) {
            return Feature::isActive(self::JSON_LD_FLAG);
        }

        // Kein Flag mehr: Shopware hat die strukturierten Daten fest eingebaut (ab 6.8).
        // Der Kern gibt sie dann immer aus.
        return true;
    }
}
