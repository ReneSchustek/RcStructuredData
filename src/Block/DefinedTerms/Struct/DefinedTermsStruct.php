<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Block\DefinedTerms\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Trägt einen Begriffssatz eines rc-defined-terms-Slots ins Storefront-Template.
 *
 * Name, Erklärung und Begriffe stehen sichtbar auf der Seite; der `DefinedTermSet`-Knoten
 * entsteht aus derselben Quelle. Anzeige und Auszeichnung können deshalb nicht auseinanderlaufen.
 */
final class DefinedTermsStruct extends Struct
{
    /**
     * @param list<array{term: string, definition: string}> $terms
     */
    public function __construct(
        private readonly string $name = '',
        private readonly string $description = '',
        private readonly array $terms = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return list<array{term: string, definition: string}>
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function getApiAlias(): string
    {
        return 'rc_schema_defined_terms';
    }
}
