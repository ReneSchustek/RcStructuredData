<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

/**
 * Setzt aus den getaggten Providern den vollständigen JSON-LD-Graphen zusammen.
 *
 * Ablauf: passende Provider per supports() filtern, ihre Knoten einsammeln, über "@id"
 * deduplizieren (Singletons), anschließend die Seiten-Knoten mit ihren Unterknoten
 * (FAQPage, VideoObject) verdrahten. Die Verdrahtung ist der "Hook", über den 02b/02c
 * ihre Knoten allein durch Hinzufügen eines Providers an die Seite hängen.
 */
final class GraphBuilder
{
    private const CONTEXT = 'https://schema.org';

    /**
     * Seiten-Knoten, an die Unterknoten gehängt werden.
     *
     * @var list<string>
     */
    private const PAGE_TYPES = ['CollectionPage', 'WebPage'];

    /**
     * Abbildung "verknüpfter Knoten-Typ => Eigenschaft am Seiten-Knoten".
     * Die Verknüpfung wird erst gesetzt, wenn der Zielknoten wirklich existiert — so
     * entstehen keine hängenden "@id"-Referenzen. Erweiterung erfolgt hier zentral.
     *
     * @var array<string, array{property: string, multiple: bool}>
     */
    private const RELATIONS = [
        'WebSite' => ['property' => 'isPartOf', 'multiple' => false],
        'BreadcrumbList' => ['property' => 'breadcrumb', 'multiple' => false],
        'FAQPage' => ['property' => 'mainEntity', 'multiple' => false],
        'BlogPosting' => ['property' => 'mainEntity', 'multiple' => false],
        'VideoObject' => ['property' => 'hasPart', 'multiple' => true],
    ];

    /**
     * @param iterable<SchemaNodeProvider> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return array<string, mixed>|null Vollständiges Dokument oder null, wenn kein Knoten anfällt
     */
    public function build(SchemaContext $context): ?array
    {
        $nodes = $this->collectNodes($context);

        if ($nodes === []) {
            return null;
        }

        $nodes = $this->wireRelations($nodes);
        $nodes = $this->stripDanglingReferences($nodes);

        return [
            '@context' => self::CONTEXT,
            '@graph' => array_values($nodes),
        ];
    }

    /**
     * @return array<string, array<string, mixed>> Knoten, indiziert über ihre "@id"
     */
    private function collectNodes(SchemaContext $context): array
    {
        $nodes = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($context)) {
                continue;
            }

            foreach ($provider->provide($context) as $node) {
                $id = $node['@id'] ?? null;
                if (!\is_string($id) || $id === '') {
                    continue;
                }

                // Erstes Vorkommen gewinnt — Singletons bleiben dadurch eindeutig.
                $nodes[$id] ??= $node;
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function wireRelations(array $nodes): array
    {
        $pageId = $this->findPageNodeId($nodes);
        if ($pageId === null) {
            return $nodes;
        }

        // Referenzen je Ziel-Property sammeln: mehrere Knoten-Typen können auf dieselbe
        // Property zeigen (FAQPage und BlogPosting beide auf `mainEntity`). Ohne Sammeln
        // würde der zuletzt iterierte Typ den vorherigen still überschreiben und dessen
        // Knoten unverdrahtet im Graph hängen lassen.
        $byProperty = [];
        foreach (self::RELATIONS as $type => $relation) {
            $references = $this->referencesForType($nodes, $type);
            if ($references === []) {
                continue;
            }

            $property = $relation['property'];
            $byProperty[$property] ??= ['multiple' => false, 'references' => []];
            foreach ($references as $reference) {
                $byProperty[$property]['references'][] = $reference;
            }
            if ($relation['multiple']) {
                $byProperty[$property]['multiple'] = true;
            }
        }

        foreach ($byProperty as $property => $data) {
            $references = $data['references'];
            // Array, sobald die Property als multiple deklariert ist ODER mehrere Typen
            // darauf verweisen — sonst der Einzelwert (schema.org-konform, bestehendes Verhalten).
            $nodes[$pageId][$property] = ($data['multiple'] || \count($references) > 1)
                ? $references
                : $references[0];
        }

        return $nodes;
    }

    /**
     * Entfernt reine "@id"-Referenzen, deren Zielknoten nicht im Graph liegt.
     *
     * Die Seiten-Verdrahtung (RELATIONS) setzt Referenzen bereits nur bei existierendem Ziel;
     * unbedingte Referenzen aus den Providern selbst (z. B. `publisher => {@id: organization}`)
     * können dagegen ins Leere zeigen, wenn ihr Singleton gar nicht erzeugt wurde (etwa eine
     * Organization ohne gepflegten Namen). Google beanstandet solche "referenced @id not found".
     *
     * @param array<string, array<string, mixed>> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function stripDanglingReferences(array $nodes): array
    {
        $known = [];
        foreach (array_keys($nodes) as $id) {
            $known[$id] = true;
        }

        foreach ($nodes as $id => $node) {
            foreach ($node as $property => $value) {
                if ($property === '@id') {
                    continue;
                }

                if ($this->isReference($value)) {
                    if (!isset($known[$value['@id']])) {
                        unset($nodes[$id][$property]);
                    }

                    continue;
                }

                if (\is_array($value) && array_is_list($value) && $value !== []) {
                    $kept = [];
                    foreach ($value as $item) {
                        if ($this->isReference($item) && !isset($known[$item['@id']])) {
                            continue;
                        }
                        $kept[] = $item;
                    }

                    if ($kept === []) {
                        unset($nodes[$id][$property]);
                    } elseif (\count($kept) !== \count($value)) {
                        $nodes[$id][$property] = $kept;
                    }
                }
            }
        }

        return $nodes;
    }

    /**
     * Reine Referenz = Array, das ausschließlich einen String-"@id" trägt (keine Inline-Objekte
     * wie `{@type: ImageObject, url: …}`, die bleiben unangetastet).
     */
    private function isReference(mixed $value): bool
    {
        return \is_array($value)
            && \count($value) === 1
            && isset($value['@id'])
            && \is_string($value['@id']);
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     */
    private function findPageNodeId(array $nodes): ?string
    {
        foreach ($nodes as $id => $node) {
            if (\in_array($node['@type'] ?? null, self::PAGE_TYPES, true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     *
     * @return list<array<string, string>>
     */
    private function referencesForType(array $nodes, string $type): array
    {
        $references = [];

        foreach ($nodes as $id => $node) {
            if (($node['@type'] ?? null) === $type) {
                $references[] = ['@id' => $id];
            }
        }

        return $references;
    }
}
