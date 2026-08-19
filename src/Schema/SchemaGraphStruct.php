<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Page-Extension, die den fertigen JSON-LD-Graphen ins Storefront-Template transportiert.
 *
 * Das Template gibt ausschließlich diese Daten als einen "@graph"-Block aus und enthält
 * keine Aufbau-Logik (SoC).
 */
final class SchemaGraphStruct extends Struct
{
    public const EXTENSION_NAME = 'rcSchemaGraph';

    /**
     * @param array<string, mixed> $graph       Vollständiges JSON-LD-Dokument inkl. "@context" und "@graph"
     * @param list<string>         $diagnostics Vollständigkeits-Hinweise; nur bei aktivem Debug-Schalter befüllt
     */
    public function __construct(
        private readonly array $graph,
        private readonly array $diagnostics = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGraph(): array
    {
        return $this->graph;
    }

    /**
     * @return list<string>
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function getApiAlias(): string
    {
        return 'rc_schema_graph';
    }
}
