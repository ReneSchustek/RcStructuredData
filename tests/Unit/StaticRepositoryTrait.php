<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Ein Ersatz-Repository mit festgelegter Sammlungsart, damit die statische Analyse die Abhängigkeiten der geprüften
 * Klassen zuordnen kann.
 */
trait StaticRepositoryTrait
{
    /**
     * @template TCollection of EntityCollection
     *
     * @param class-string<TCollection> $collectionClass legt nur die Sammlungsart für die Analyse fest
     * @param array<mixed>              $searches        die Antworten der Suchen, in ihrer Reihenfolge
     *
     * @return StaticEntityRepository<TCollection>
     */
    private static function repository(string $collectionClass, array $searches = []): StaticEntityRepository
    {
        /** @var StaticEntityRepository<TCollection> $repository */
        $repository = new StaticEntityRepository($searches);

        return $repository;
    }
}
