<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\GraphBuilder;
use Ruhrcoder\RcStructuredData\Schema\SchemaContext;
use Ruhrcoder\RcStructuredData\Schema\SchemaNodeProvider;

/**
 * Prüft Zusammenbau, Deduplizierung und Verdrahtung der @id-Bezüge im GraphBuilder.
 */
class GraphBuilderTest extends TestCase
{
    use SchemaContextTrait;

    public function testReturnsNullWhenNoNodeIsProduced(): void
    {
        $builder = new GraphBuilder([
            $this->provider(false, [['@type' => 'WebSite', '@id' => 'a']]),
        ]);

        static::assertNull($builder->build($this->makeContext()));
    }

    public function testAssemblesGraphWithContext(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
        ]);

        $result = $builder->build($this->makeContext());

        static::assertNotNull($result);
        static::assertSame('https://schema.org', $result['@context']);
        static::assertCount(1, $result['@graph']);
        static::assertSame('CollectionPage', $result['@graph'][0]['@type']);
    }

    public function testDeduplicatesByIdFirstWins(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'Organization', '@id' => 'org', 'name' => 'first']]),
            $this->provider(true, [['@type' => 'Organization', '@id' => 'org', 'name' => 'second']]),
        ]);

        $result = $builder->build($this->makeContext());

        static::assertNotNull($result);
        static::assertCount(1, $result['@graph']);
        static::assertSame('first', $result['@graph'][0]['name']);
    }

    public function testIgnoresNodesWithoutId(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
            $this->provider(true, [['@type' => 'Thing']]),
        ]);

        $result = $builder->build($this->makeContext());

        static::assertNotNull($result);
        static::assertCount(1, $result['@graph']);
    }

    public function testWiresPageRelations(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
            $this->provider(true, [['@type' => 'WebSite', '@id' => 'web']]),
            $this->provider(true, [['@type' => 'BreadcrumbList', '@id' => 'bc']]),
            $this->provider(true, [['@type' => 'FAQPage', '@id' => 'faq']]),
            $this->provider(true, [
                ['@type' => 'VideoObject', '@id' => 'vid-1'],
                ['@type' => 'VideoObject', '@id' => 'vid-2'],
            ]),
        ]);

        $page = $this->findNode($builder->build($this->makeContext()), 'page');

        static::assertSame(['@id' => 'web'], $page['isPartOf']);
        static::assertSame(['@id' => 'bc'], $page['breadcrumb']);
        static::assertSame(['@id' => 'faq'], $page['mainEntity']);
        static::assertSame([['@id' => 'vid-1'], ['@id' => 'vid-2']], $page['hasPart']);
    }

    public function testDoesNotSetRelationsWhenTargetsMissing(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
        ]);

        $page = $this->findNode($builder->build($this->makeContext()), 'page');

        static::assertArrayNotHasKey('mainEntity', $page);
        static::assertArrayNotHasKey('hasPart', $page);
        static::assertArrayNotHasKey('breadcrumb', $page);
        static::assertArrayNotHasKey('isPartOf', $page);
    }

    public function testMergesMultipleTypesTargetingSameProperty(): void
    {
        // FAQPage und BlogPosting zeigen beide auf `mainEntity` (Blog-Detailseite mit rc-faq-Block).
        // Beide müssen verlinkt bleiben, statt dass der zuletzt verdrahtete Typ den anderen still
        // überschreibt und dessen Knoten unverdrahtet im Graph zurücklässt.
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'WebPage', '@id' => 'page']]),
            $this->provider(true, [['@type' => 'FAQPage', '@id' => 'faq']]),
            $this->provider(true, [['@type' => 'BlogPosting', '@id' => 'blog']]),
        ]);

        $page = $this->findNode($builder->build($this->makeContext()), 'page');

        static::assertSame([['@id' => 'faq'], ['@id' => 'blog']], $page['mainEntity']);
    }

    public function testStripsDanglingReferenceWhenTargetMissing(): void
    {
        // `publisher` zeigt auf eine Organization, die nicht im Graph liegt (kein gepflegter Name)
        // → die tote Referenz wird entfernt (kein von Google beanstandetes „referenced @id not found").
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
            $this->provider(true, [['@type' => 'WebSite', '@id' => 'web', 'publisher' => ['@id' => 'org']]]),
        ]);

        $web = $this->findNode($builder->build($this->makeContext()), 'web');

        static::assertArrayNotHasKey('publisher', $web);
    }

    public function testKeepsReferenceWhenTargetExists(): void
    {
        $builder = new GraphBuilder([
            $this->provider(true, [['@type' => 'CollectionPage', '@id' => 'page']]),
            $this->provider(true, [['@type' => 'WebSite', '@id' => 'web', 'publisher' => ['@id' => 'org']]]),
            $this->provider(true, [['@type' => 'Organization', '@id' => 'org', 'name' => 'Shop']]),
        ]);

        $web = $this->findNode($builder->build($this->makeContext()), 'web');

        static::assertSame(['@id' => 'org'], $web['publisher']);
    }

    /**
     * @param array<string, mixed>|null $result
     *
     * @return array<string, mixed>
     */
    private function findNode(?array $result, string $id): array
    {
        static::assertNotNull($result);
        foreach ($result['@graph'] as $node) {
            if (($node['@id'] ?? null) === $id) {
                return $node;
            }
        }

        static::fail(sprintf('Node "%s" not found in graph.', $id));
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function provider(bool $supports, array $nodes): SchemaNodeProvider
    {
        return new class ($supports, $nodes) implements SchemaNodeProvider {
            /**
             * @param list<array<string, mixed>> $nodes
             */
            public function __construct(
                private readonly bool $supports,
                private readonly array $nodes,
            ) {
            }

            public function supports(SchemaContext $context): bool
            {
                return $this->supports;
            }

            public function provide(SchemaContext $context): array
            {
                return $this->nodes;
            }
        };
    }
}
