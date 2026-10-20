<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\DefinedTermsCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\DefinedTermSetNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Prüft die Begriffslisten: je Baustein ein Satz mit eigenem Namen, unvollständige Sätze und Begriffe fallen weg, ohne
 * dass in der Nummerierung eine Lücke entsteht.
 */
class DefinedTermSetNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testNotSupportedWithoutCmsPage(): void
    {
        static::assertFalse($this->provider()->supports($this->makeContext()));
    }

    public function testBuildsOneSetPerSlot(): void
    {
        $page = $this->makeCmsPage(
            $this->makeDefinedTermsSlot('Vorgaben für sichere Geländer', [
                ['term' => 'Geländerhöhe 90 cm'],
                ['term' => 'Leitereffekt'],
            ]),
            $this->makeSlot('text'),
            $this->makeDefinedTermsSlot('Werkstoffe', [['term' => 'Edelstahl V2A']]),
        );
        $context = $this->makeContext(pageUrl: 'https://shop.example/seite', cmsPage: $page);

        static::assertTrue($this->provider()->supports($context));

        $nodes = $this->provider()->provide($context);
        static::assertCount(2, $nodes);

        static::assertSame('DefinedTermSet', $nodes[0]['@type']);
        static::assertSame('https://shop.example/seite#begriffe-1', $nodes[0]['@id']);
        static::assertSame('Vorgaben für sichere Geländer', $nodes[0]['name']);
        static::assertCount(2, $nodes[0]['hasDefinedTerm']);
        static::assertSame('DefinedTerm', $nodes[0]['hasDefinedTerm'][0]['@type']);
        static::assertSame('Geländerhöhe 90 cm', $nodes[0]['hasDefinedTerm'][0]['name']);

        static::assertSame('https://shop.example/seite#begriffe-2', $nodes[1]['@id']);
        static::assertSame('Werkstoffe', $nodes[1]['name']);
    }

    /**
     * Zwei Begriffssätze werden bewusst nicht verschmolzen (anders als die FAQ). Jeder Satz
     * trägt einen eigenen Namen, und der ist bei einem Begriffssatz die halbe Aussage.
     */
    public function testTwoSetsKeepTheirOwnNames(): void
    {
        $page = $this->makeCmsPage(
            $this->makeDefinedTermsSlot('Normen', [['term' => 'DIN 18065']]),
            $this->makeDefinedTermsSlot('Werkstoffe', [['term' => 'V4A']]),
        );

        $nodes = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page));

        static::assertSame(['Normen', 'Werkstoffe'], array_column($nodes, 'name'));
    }

    /**
     * Dieselbe Regel wie beim VideoObject ohne Datum: lieber kein Knoten als ein unvollständiger.
     */
    public function testSetWithoutNameIsNotRendered(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot(null, [['term' => 'Irgendwas']]));

        static::assertSame(
            [],
            $this->provider()->provide(
                $this->makeContext(
                    pageUrl: 'https://shop.example/x',
                    cmsPage: $page
                )
            )
        );
    }

    public function testSetWithoutTermsIsNotRendered(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot('Leerer Satz', []));

        static::assertSame(
            [],
            $this->provider()->provide(
                $this->makeContext(
                    pageUrl: 'https://shop.example/x',
                    cmsPage: $page
                )
            )
        );
    }

    /**
     * Ein ausgelassener Satz darf keine Lücke in der Nummerierung hinterlassen, sonst zeigt eine
     * spätere Verknüpfung auf ein `@id`, das es nicht gibt.
     */
    public function testSkippedSetLeavesNoGapInTheNumbering(): void
    {
        $page = $this->makeCmsPage(
            $this->makeDefinedTermsSlot(null, [['term' => 'ohne Namen']]),
            $this->makeDefinedTermsSlot('Zweiter Satz', [['term' => 'Begriff']]),
        );

        $nodes = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page));

        static::assertCount(1, $nodes);
        static::assertSame('https://shop.example/x#begriffe-1', $nodes[0]['@id']);
    }

    public function testTermWithoutNameIsDropped(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot('Satz', [
            ['term' => '   '],
            ['term' => 'Gültiger Begriff'],
        ]));

        $nodes = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page));

        static::assertCount(1, $nodes[0]['hasDefinedTerm']);
        static::assertSame('Gültiger Begriff', $nodes[0]['hasDefinedTerm'][0]['name']);
    }

    /**
     * Die Erklärung ist freiwillig, denn viele Begriffssätze tragen nur Namen.
     */
    public function testDefinitionIsOptionalButUsedWhenPresent(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot('Satz', [
            ['term' => 'Ohne Erklärung'],
            ['term' => 'Mit Erklärung', 'definition' => '<p>Was es bedeutet.</p>'],
        ]));

        $terms = $this->provider()->provide(
            $this->makeContext(
                pageUrl: 'https://shop.example/x',
                cmsPage: $page
            )
        )[0]['hasDefinedTerm'];

        static::assertArrayNotHasKey('description', $terms[0]);
        static::assertSame('Was es bedeutet.', $terms[1]['description']);
    }

    /**
     * Google erlaubt Auszeichnung in `acceptedAnswer.text`, für `DefinedTerm.description` nicht.
     * Dort stünde ein `<p>` als sichtbarer Text im Suchergebnis. Die Seite behält ihre
     * Auszeichnung, das Schema bekommt reinen Text.
     */
    public function testDefinitionReachesTheSchemaAsPlainText(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot('Satz', [
            ['term' => 'Begriff', 'definition' => '<p>Satz eins.</p><p>Satz zwei.</p>'],
        ]));

        $terms = $this->provider()->provide(
            $this->makeContext(
                pageUrl: 'https://shop.example/x',
                cmsPage: $page
            )
        )[0]['hasDefinedTerm'];

        static::assertSame('Satz eins. Satz zwei.', $terms[0]['description']);
    }

    public function testDefinitionWithOnlyMarkupIsDropped(): void
    {
        $page = $this->makeCmsPage($this->makeDefinedTermsSlot('Satz', [
            ['term' => 'Begriff', 'definition' => '<p> </p>'],
        ]));

        $terms = $this->provider()->provide(
            $this->makeContext(
                pageUrl: 'https://shop.example/x',
                cmsPage: $page
            )
        )[0]['hasDefinedTerm'];

        static::assertArrayNotHasKey('description', $terms[0]);
    }

    public function testSetDescriptionIsUsedWhenPresent(): void
    {
        $page = $this->makeCmsPage(
            $this->makeDefinedTermsSlot('Satz', [['term' => 'Begriff']], 'Worum es hier geht.')
        );

        $node = $this->provider()->provide($this->makeContext(pageUrl: 'https://shop.example/x', cmsPage: $page))[0];

        static::assertSame('Worum es hier geht.', $node['description']);
    }

    private function provider(): DefinedTermSetNodeProvider
    {
        return new DefinedTermSetNodeProvider(
            new CmsSlotIterator(),
            new DefinedTermsCmsBlockExtractor($this->passThroughSanitizer()),
            new IdFactory(),
        );
    }

    /**
     * Der Sanitizer gehört nicht zum Prüfgegenstand dieses Tests. Die Doublette reicht den Text
     * durch, damit die Zusicherungen den Knotenaufbau messen.
     */
    private function passThroughSanitizer(): HtmlSanitizer
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        return $sanitizer;
    }
}
