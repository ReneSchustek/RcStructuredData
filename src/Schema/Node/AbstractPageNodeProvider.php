<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema\Node;

use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Gemeinsame Basis für die Seiten-Knoten (CollectionPage, WebPage).
 *
 * Der einzige Unterschied ist der Schema-Typ und die Seiten-Bedingung; die Knoten-Struktur
 * (Identität, Sprache, Beschreibung, additionalProperty) ist identisch (DRY). Die Bezüge
 * isPartOf/breadcrumb/mainEntity/hasPart setzt der GraphBuilder, sobald die Zielknoten
 * existieren.
 */
abstract class AbstractPageNodeProvider implements SchemaNodeProvider
{
    public function __construct(
        protected readonly IdFactory $idFactory,
    ) {
    }

    abstract protected function pageType(): string;

    public function provide(SchemaContext $context): array
    {
        $node = [
            '@type' => $this->pageType(),
            '@id' => $this->idFactory->page($context),
            'url' => $context->getPageUrl(),
            'name' => $context->getName(),
            'inLanguage' => $context->getLocaleCode(),
        ];

        $description = $context->getDescription();
        if ($description !== '') {
            $node['description'] = $description;
        }

        $additionalProperty = $this->buildAdditionalProperty($context);
        if ($additionalProperty !== []) {
            $node['additionalProperty'] = $additionalProperty;
        }

        return [$node];
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildAdditionalProperty(SchemaContext $context): array
    {
        $properties = [];

        foreach ($context->getAdditionalProperties() as $property) {
            $name = trim($property['name']);
            $value = trim($property['value']);
            if ($name === '' || $value === '') {
                continue;
            }

            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => $value,
            ];
        }

        return $properties;
    }
}
