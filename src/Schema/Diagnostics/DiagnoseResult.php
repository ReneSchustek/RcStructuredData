<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Diagnostics;

/**
 * Ergebnis einer Kategorie-Diagnose: gefundene Kategorie, erzeugter Graph, Lücken je Knoten und
 * Hinweise zur Seite selbst (handgeschriebenes JSON-LD, FAQ ohne Baustein).
 */
final class DiagnoseResult
{
    /**
     * @param array<string, mixed>|null $graph
     * @param list<array{type: string, id: string, missingRequired: list<string>, missingRecommended: list<string>}> $findings
     * @param list<array{code: string, params: array<string, int|string>, message: string}> $warnings
     */
    private function __construct(
        public readonly bool $pageFound,
        public readonly string $pageName,
        public readonly ?array $graph,
        public readonly array $findings,
        public readonly array $warnings = [],
    ) {
    }

    public static function notFound(): self
    {
        return new self(false, '', null, [], []);
    }

    /**
     * @param array<string, mixed>|null $graph
     * @param list<array{type: string, id: string, missingRequired: list<string>, missingRecommended: list<string>}> $findings
     * @param list<array{code: string, params: array<string, int|string>, message: string}> $warnings
     */
    public static function analysed(string $pageName, ?array $graph, array $findings, array $warnings = []): self
    {
        return new self(true, $pageName, $graph, $findings, $warnings);
    }

    public function nodeCount(): int
    {
        $graph = $this->graph['@graph'] ?? null;

        return \is_array($graph) ? \count($graph) : 0;
    }
}
