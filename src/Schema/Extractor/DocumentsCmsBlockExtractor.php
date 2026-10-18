<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Extractor;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Liest die Einträge eines rc-documents-CMS-Slots: Nachweise wie Prüfzeugnisse, die als Datei in
 * der Medienverwaltung liegen.
 *
 * **Warum es diesen Baustein gibt.** Stehen ein bauaufsichtliches Prüfzeugnis und sein
 * Verlängerungsbescheid als Verweise in einem freien Textblock, die Daten dazu in einer Tabelle
 * daneben, lässt sich daraus kein `DigitalDocument` ableiten, ohne Fließtext zu deuten. Was ein
 * Verweis *ist*, sagt ein Verweis nicht, und die Auszeichnung bliebe dem handgeschriebenen JSON-LD
 * überlassen. Dieser Baustein macht die Nachweise zu Daten: Die Liste steht
 * sichtbar auf der Seite, das Schema ist ihr Abbild.
 *
 * Der Extraktor liefert nur die gepflegten Rohwerte. Die Adresse der Datei kennt erst der
 * `DocumentsResolver`, nachdem er das Medium geladen hat.
 */
final class DocumentsCmsBlockExtractor implements CmsBlockSchemaExtractor
{
    private const TITLE_KEY = 'documentsTitle';

    private const DOCUMENTS_KEY = 'documents';

    /**
     * Die Redaktion pflegt Tage, nicht Zeitpunkte. Kommt ein Zeitpunkt an, wird er in dieser Zone
     * gelesen — siehe `cleanDate()`.
     */
    private const EDITORIAL_TIMEZONE = 'Europe/Berlin';

    public function supports(CmsSlotEntity $slot): bool
    {
        return $slot->getType() === RcStructuredData::CMS_ELEMENT_DOCUMENTS;
    }

    /**
     * @return list<array{name: string, description: string, mediaId: string, datePublished: string, expires: string}>
     */
    public function extract(CmsSlotEntity $slot): array
    {
        $rawDocuments = $slot->getFieldConfig()->get(self::DOCUMENTS_KEY)?->getValue();
        if (!\is_array($rawDocuments)) {
            return [];
        }

        $documents = [];
        foreach ($rawDocuments as $rawDocument) {
            if (!\is_array($rawDocument)) {
                continue;
            }

            // **Name und Datei sind Pflicht.** Ein Nachweis ohne Namen ist ein nackter Verweis,
            // einer ohne Datei ein Versprechen ohne Beleg — beides fällt weg.
            $name = $this->cleanText($rawDocument['name'] ?? null);
            // Kleinschreibung vor der Prüfung: `Uuid::isValid()` lehnt Großbuchstaben ab.
            $mediaId = strtolower($this->cleanText($rawDocument['mediaId'] ?? null));
            if ($name === '' || !Uuid::isValid($mediaId)) {
                continue;
            }

            $documents[] = [
                'name' => $name,
                'description' => $this->cleanText($rawDocument['description'] ?? null),
                'mediaId' => $mediaId,
                'datePublished' => $this->cleanDate($rawDocument['datePublished'] ?? null),
                'expires' => $this->cleanDate($rawDocument['expires'] ?? null),
            ];
        }

        return $documents;
    }

    public function extractTitle(CmsSlotEntity $slot): string
    {
        return $this->cleanText($slot->getFieldConfig()->get(self::TITLE_KEY)?->getValue());
    }

    /**
     * @return list<string>
     */
    public function extractMediaIds(CmsSlotEntity $slot): array
    {
        return array_values(array_unique(array_column($this->extract($slot), 'mediaId')));
    }

    /**
     * Liefert den Tag als `JJJJ-MM-TT` oder leer.
     *
     * **Warum ein Zeitpunkt umgerechnet wird, statt ihn abzuschneiden:** Ein Datumsfeld im
     * Backoffice kann „4. Juli, 0 Uhr" als `2026-07-03T22:00:00.000Z` speichern. Die ersten zehn
     * Zeichen wären dann der 3. Juli — ein Prüfzeugnis, das einen Tag früher abläuft, als darauf
     * steht. In der Zone der Redaktion gelesen, ist es wieder der 4.
     */
    private function cleanDate(mixed $value): string
    {
        $text = $this->cleanText($value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

            return $day !== false && $day->format('Y-m-d') === $text ? $text : '';
        }

        try {
            // Ein Zeitpunkt ohne Zone gilt in der Zeit der Redaktion, nicht in der des Servers.
            $moment = new DateTimeImmutable($text, new DateTimeZone(self::EDITORIAL_TIMEZONE));
        } catch (Exception) {
            return '';
        }

        return $moment->setTimezone(new DateTimeZone(self::EDITORIAL_TIMEZONE))->format('Y-m-d');
    }

    private function cleanText(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }
}
