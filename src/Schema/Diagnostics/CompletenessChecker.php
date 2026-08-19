<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

/**
 * Prüft einen fertigen JSON-LD-Graphen gegen die von Google geforderten/empfohlenen Felder.
 *
 * Kennt je Schema-Typ, den das Plugin erzeugt, die Pflicht- und Empfehlungs-Felder und meldet
 * pro Knoten die Lücken. Reine Analyse ohne Ausgabe (SoC) — die Formatierung (Debug-Kommentar,
 * Konsole) übernehmen die Aufrufer über describe().
 */
final class CompletenessChecker
{
    /**
     * @var array<string, array{required: list<string>, recommended: list<string>}>
     */
    private const REQUIREMENTS = [
        'VideoObject' => [
            'required' => ['name', 'description', 'thumbnailUrl', 'uploadDate'],
            'recommended' => ['duration', 'contentUrl', 'embedUrl'],
        ],
        'FAQPage' => [
            'required' => ['mainEntity'],
            'recommended' => [],
        ],
        'Organization' => [
            'required' => ['name', 'url'],
            'recommended' => ['logo'],
        ],
        'CollectionPage' => [
            'required' => ['name', 'url'],
            'recommended' => ['description'],
        ],
        'WebPage' => [
            'required' => ['name', 'url'],
            'recommended' => ['description'],
        ],
    ];

    /**
     * @param array<string, mixed> $document Vollständiges JSON-LD-Dokument (inkl. "@graph")
     *
     * @return list<array{type: string, id: string, missingRequired: list<string>, missingRecommended: list<string>}>
     */
    public function check(array $document): array
    {
        $findings = [];

        foreach ($this->graphNodes($document) as $node) {
            $type = $node['@type'] ?? null;
            if (!\is_string($type) || !isset(self::REQUIREMENTS[$type])) {
                continue;
            }

            $missingRequired = $this->missingFields($node, self::REQUIREMENTS[$type]['required']);
            $missingRecommended = $this->missingFields($node, self::REQUIREMENTS[$type]['recommended']);
            if ($missingRequired === [] && $missingRecommended === []) {
                continue;
            }

            $findings[] = [
                'type' => $type,
                'id' => \is_string($node['@id'] ?? null) ? $node['@id'] : '',
                'missingRequired' => $missingRequired,
                'missingRecommended' => $missingRecommended,
            ];
        }

        return $findings;
    }

    /**
     * Wandelt die Befunde in menschenlesbare Zeilen (Debug-Kommentar, Konsole).
     *
     * @param array<string, mixed> $document
     *
     * @return list<string>
     */
    public function describe(array $document): array
    {
        $lines = [];

        foreach ($this->check($document) as $finding) {
            $parts = [];
            if ($finding['missingRequired'] !== []) {
                $parts[] = 'Pflicht: ' . implode(', ', $finding['missingRequired']);
            }
            if ($finding['missingRecommended'] !== []) {
                $parts[] = 'empfohlen: ' . implode(', ', $finding['missingRecommended']);
            }

            $label = $finding['id'] !== '' ? $finding['type'] . ' (' . $finding['id'] . ')' : $finding['type'];
            $lines[] = $label . ' — fehlt: ' . implode('; ', $parts);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array<string, mixed>>
     */
    private function graphNodes(array $document): array
    {
        $graph = $document['@graph'] ?? null;
        if (!\is_array($graph)) {
            return [];
        }

        $nodes = [];
        foreach ($graph as $node) {
            if (\is_array($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $fields
     *
     * @return list<string>
     */
    private function missingFields(array $node, array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if ($this->isEmpty($node[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (\is_string($value)) {
            return trim($value) === '';
        }

        if (\is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
