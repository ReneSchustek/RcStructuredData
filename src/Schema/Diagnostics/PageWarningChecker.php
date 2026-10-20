<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

use Ruhrcoder\RcStructuredData\RcStructuredData;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;

/**
 * Meldet drei Zustände, in denen Graph und Seite nicht zusammenpassen.
 *
 * Der CompletenessChecker sieht nur den Graphen, den dieses Plugin baut. Die drei Fehler unten liegen im Verhältnis
 * zwischen Graph und Seite und sind am Graphen allein nicht zu erkennen:
 *
 * 1. Handgeschriebenes JSON-LD auf derselben Seite. Steht in einem Baustein ein eigener `ld+json`-Block, trägt die
 * Seite
 *    zwei Auszeichnungen, die auseinanderlaufen können, etwa Fragen, die auf der Seite gar nicht zu sehen sind.
 * 2. Ein Video ohne Knoten. Fehlt einem YouTube-Baustein das Veröffentlichungsdatum, gibt das Plugin für ihn keinen
 *    Knoten aus: Ein unvollständiges VideoObject ist für Google ein kritischer Fehler, ein fehlendes neutral. Die Meldung
 *    nennt, welches Video ein Datum braucht.
 * 3. `FAQPage` ohne FAQ-Baustein. Meldet der Graph eine FAQ, ohne dass ein `rc-faq`-Baustein auf der Seite steht,
 * stammt
 *    sie aus einer anderen Quelle und ist womöglich für keinen Besucher sichtbar.
 *
 * Alle drei Prüfungen laufen über die ohnehin geladenen Bausteine und kosten keine Abfrage.
 */
final class PageWarningChecker
{
    public const WARNING_HANDWRITTEN_JSON_LD = 'handwritten_json_ld';
    public const WARNING_FAQ_WITHOUT_BLOCK = 'faq_without_block';
    public const WARNING_VIDEO_WITHOUT_UPLOAD_DATE = 'video_without_upload_date';

    /**
     * Bewusst nicht auf `moorl-twig` eingegrenzt: Handgeschriebenes JSON-LD kann in jedem Slot
     * stehen, der freies Markup zulässt. Gemeldet wird deshalb der Slot-Typ, in dem es steckt.
     */
    private const JSON_LD_MARKER = 'application/ld+json';

    public function __construct(
        private readonly CmsSlotIterator $slotIterator,
        private readonly YoutubeVideoExtractor $videoExtractor,
    ) {
    }

    /**
     * @param array<string, mixed>|null $graph
     *
     * Die Verwaltung baut den Text aus `code` und `params` über ihre Textbausteine (`rc-schema.diagnose.warning.*`,
     * de-DE und en-GB). `message` bleibt deutsch für die Konsole und den Kommentar im Seitenquelltext.
     *
     * @return list<array{code: string, params: array<string, int|string>, message: string}>
     */
    public function check(?array $graph, ?CmsPageEntity $page): array
    {
        $slots = $this->slotIterator->slots($page);

        $warnings = [];

        $slotTypes = $this->slotTypesWithHandwrittenJsonLd($slots);
        if ($slotTypes !== []) {
            $warnings[] = $this->createHandwrittenJsonLdWarning($slotTypes);
        }

        $omittedVideoIds = $this->videosWithoutNode($graph, $slots);
        if ($omittedVideoIds !== []) {
            $warnings[] = $this->createVideoWithoutUploadDateWarning($omittedVideoIds);
        }

        if ($this->graphHasFaqPage($graph) && !$this->hasFaqBlock($slots)) {
            $warnings[] = $this->createFaqWithoutBlockWarning();
        }

        return $warnings;
    }

    /**
     * @param list<string> $slotTypes
     *
     * @return array{code: string, params: array<string, int|string>, message: string}
     */
    private function createHandwrittenJsonLdWarning(array $slotTypes): array
    {
        return [
            'code' => self::WARNING_HANDWRITTEN_JSON_LD,
            'params' => ['types' => implode(', ', $slotTypes)],
            'message' => sprintf(
                'Auf dieser Seite steht handgeschriebenes JSON-LD (%s). Zusammen mit dem erzeugten '
                . 'Graphen trägt die Seite zwei Auszeichnungen, die auseinanderlaufen können.',
                implode(', ', $slotTypes)
            ),
        ];
    }

    /**
     * @param list<string> $omittedVideoIds
     *
     * @return array{code: string, params: array<string, int|string>, message: string}
     */
    private function createVideoWithoutUploadDateWarning(array $omittedVideoIds): array
    {
        return [
            'code' => self::WARNING_VIDEO_WITHOUT_UPLOAD_DATE,
            'params' => ['videos' => implode(', ', $omittedVideoIds), 'count' => \count($omittedVideoIds)],
            'message' => sprintf(
                'Für %s ist kein VideoObject entstanden — es fehlt das Veröffentlichungsdatum. '
                . 'Ein unvollständiger Knoten wäre für Google ein kritischer Fehler; gepflegt wird '
                . 'das Datum am Baustein oder an der Seite.',
                \count($omittedVideoIds) === 1
                    ? 'das Video ' . $omittedVideoIds[0]
                    : 'die Videos ' . implode(', ', $omittedVideoIds)
            ),
        ];
    }

    /**
     * @return array{code: string, params: array<string, int|string>, message: string}
     */
    private function createFaqWithoutBlockWarning(): array
    {
        return [
            'code' => self::WARNING_FAQ_WITHOUT_BLOCK,
            'params' => [],
            'message' => 'Diese Seite meldet FAQPage, ohne einen rc-faq-Baustein zu haben. '
                . 'Die Fragen stammen dann nicht aus dem sichtbaren Seiteninhalt.',
        ];
    }

    /**
     * Die Videos der Seite, für die im Graphen kein Knoten steht.
     *
     * Verglichen wird über die Videokennung in `contentUrl` und `embedUrl`, denn die `@id` des
     * Knotens trägt sie nicht. Die Kaskade wird dabei nicht nachgebaut: Was am Ende
     * herauskam, steht im Graphen, und nur das zählt.
     *
     * @param array<string, mixed>|null $graph
     * @param list<CmsSlotEntity>       $slots
     *
     * @return list<string>
     */
    private function videosWithoutNode(?array $graph, array $slots): array
    {
        $missing = [];

        foreach ($slots as $slot) {
            if (!$this->videoExtractor->supports($slot)) {
                continue;
            }

            foreach ($this->videoExtractor->extract($slot) as $video) {
                $videoId = $video['videoId'];
                if ($videoId === '' || \in_array($videoId, $missing, true)) {
                    continue;
                }

                if (!$this->graphCarriesVideo($graph, $videoId)) {
                    $missing[] = $videoId;
                }
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed>|null $graph
     */
    private function graphCarriesVideo(?array $graph, string $videoId): bool
    {
        foreach ($this->nodesOf($graph) as $node) {
            if (($node['@type'] ?? '') !== 'VideoObject') {
                continue;
            }

            foreach (['contentUrl', 'embedUrl'] as $field) {
                $value = $node[$field] ?? '';
                if (\is_string($value) && str_contains($value, $videoId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $graph
     *
     * @return list<array<string, mixed>>
     */
    private function nodesOf(?array $graph): array
    {
        $nodes = [];

        foreach ($graph['@graph'] ?? [] as $node) {
            if (\is_array($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param list<CmsSlotEntity> $slots
     *
     * @return list<string>
     */
    private function slotTypesWithHandwrittenJsonLd(array $slots): array
    {
        $types = [];

        foreach ($slots as $slot) {
            if (!$this->containsJsonLd($slot)) {
                continue;
            }

            $type = $slot->getType();
            if (!\in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Gesucht wird in beiden Ablagen derselben Sache: `config` trägt die rohen Werte, wie sie in
     * der Datenbank stehen, `fieldConfig` die aufgelösten. Je nachdem, wie der Slot geladen wurde,
     * ist die eine oder die andere gefüllt. Geprüft werden deshalb beide.
     */
    private function containsJsonLd(CmsSlotEntity $slot): bool
    {
        $config = $slot->getConfig();
        if (\is_array($config) && $this->encodedContains($config)) {
            return true;
        }

        $fieldConfig = $slot->getFieldConfig();

        foreach ($fieldConfig->getElements() as $field) {
            if ($this->encodedContains($field->getValue())) {
                return true;
            }
        }

        return false;
    }

    /**
     * `JSON_UNESCAPED_SLASHES` ist hier nicht Geschmack: Ohne die Angabe schreibt `json_encode`
     * den Schrägstrich als `\/`, und die Suche nach `application/ld+json` findet nie etwas.
     */
    private function encodedContains(mixed $value): bool
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

        return \is_string($encoded) && str_contains($encoded, self::JSON_LD_MARKER);
    }

    /**
     * @param array<string, mixed>|null $graph
     */
    private function graphHasFaqPage(?array $graph): bool
    {
        $nodes = $graph['@graph'] ?? null;
        if (!\is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!\is_array($node)) {
                continue;
            }

            // `@type` darf laut JSON-LD auch eine Liste sein.
            $type = $node['@type'] ?? null;
            $types = \is_array($type) ? $type : [$type];

            if (\in_array('FAQPage', $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<CmsSlotEntity> $slots
     */
    private function hasFaqBlock(array $slots): bool
    {
        foreach ($slots as $slot) {
            if ($slot->getType() === RcStructuredData::CMS_ELEMENT_FAQ) {
                return true;
            }
        }

        return false;
    }
}
