<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

/**
 * Text für die strukturierten Daten. Schema-Beschreibungen sind Klartext: Ein `<p>` oder ein `&amp;` stünde sonst
 * sichtbar im Suchergebnis.
 *
 * Reine Textfunktionen ohne Zustand und ohne Abhängigkeiten; deshalb statisch.
 */
final class PlainText
{
    private function __construct()
    {
    }

    /**
     * HTML aus der Pflege als Klartext. Blockenden werden zu Leerzeichen, sonst klebte „Satz eins.Satz zwei."
     * zusammen; Entitäten werden aufgelöst, Leerraum zusammengefasst.
     */
    public static function fromHtml(mixed $html): string
    {
        if (!\is_string($html) || $html === '') {
            return '';
        }

        $withBreaks = (string) preg_replace('#</(p|div|li|br|h[1-6])\s*>|<br\s*/?>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($withBreaks), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Der erste nicht leere Text, getrimmt; sonst ''. */
    public static function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (\is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
