<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Schema;

/**
 * Vertrag für einen einzelnen Schema.org-Knoten-Lieferanten ("graph piece").
 *
 * Jeder Provider ist für genau einen Schema-Typ zuständig (SRP) und entscheidet selbst,
 * ob er auf der aktuellen Seite gebraucht wird. Neue Schema-Typen (Article, Product,
 * LocalBusiness, ...) werden durch eine weitere getaggte Implementierung ergänzt, ohne
 * bestehende Provider, den GraphBuilder oder die Ausgabe zu ändern (OCP).
 */
interface SchemaNodeProvider
{
    /**
     * Liefert dieser Provider auf der aktuellen Seite einen Knoten?
     */
    public function supports(SchemaContext $context): bool;

    /**
     * Erzeugt die Schema.org-Knoten dieses Providers.
     *
     * @return list<array<string, mixed>> Null bis mehrere Knoten, jeweils mit eindeutiger "@id"
     */
    public function provide(SchemaContext $context): array;
}
