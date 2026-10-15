<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\DefinedTerms\Struct\DefinedTermsStruct;
use Ruhrcoder\RcStructuredData\Block\Documents\Struct\DocumentsStruct;
use Ruhrcoder\RcStructuredData\Block\Faq\Struct\FaqStruct;
use Ruhrcoder\RcStructuredData\Schema\SchemaGraphStruct;

/**
 * Prüft die Datenträger, die an Seiten und Bausteinen hängen. Ihr Name in der Store-API ist Vertrag: Headless-Aufrufer
 * erkennen die Daten daran.
 */
#[CoversClass(DefinedTermsStruct::class)]
#[CoversClass(DocumentsStruct::class)]
#[CoversClass(FaqStruct::class)]
#[CoversClass(SchemaGraphStruct::class)]
final class StructsTest extends TestCase
{
    public function testDefinedTermsCarryNameDescriptionAndTerms(): void
    {
        $struct = new DefinedTermsStruct('Glossar', 'Begriffe rund ums Geländer', [['term' => 'Handlauf', 'definition' => 'Woran man sich festhält.']]);

        self::assertSame('Glossar', $struct->getName());
        self::assertSame('Begriffe rund ums Geländer', $struct->getDescription());
        self::assertSame([['term' => 'Handlauf', 'definition' => 'Woran man sich festhält.']], $struct->getTerms());
        self::assertSame(['', '', []], [(new DefinedTermsStruct())->getName(), (new DefinedTermsStruct())->getDescription(), (new DefinedTermsStruct())->getTerms()]);
    }

    public function testTheGraphCarriesItsHints(): void
    {
        $struct = new SchemaGraphStruct(['@context' => 'https://schema.org', '@graph' => []], ['Bild fehlt']);

        self::assertSame(['@context' => 'https://schema.org', '@graph' => []], $struct->getGraph());
        self::assertSame(['Bild fehlt'], $struct->getDiagnostics());
        self::assertSame([], (new SchemaGraphStruct([]))->getDiagnostics());
    }

    public function testStoreApiNamesStayStable(): void
    {
        self::assertSame('rc_schema_defined_terms', (new DefinedTermsStruct())->getApiAlias());
        self::assertSame('rc_schema_documents', (new DocumentsStruct())->getApiAlias());
        self::assertSame('rc_schema_faq', (new FaqStruct())->getApiAlias());
        self::assertSame('rc_schema_graph', (new SchemaGraphStruct([]))->getApiAlias());
    }
}
