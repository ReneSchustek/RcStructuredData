<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Diagnostics;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\PageWarningChecker;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;

class PageWarningCheckerTest extends TestCase
{
    use CmsFixtureTrait;

    public function testReportsHandwrittenJsonLdInAFreeMarkupSlot(): void
    {
        $page = $this->makeCmsPage($this->makeMarkupSlot(
            'moorl-twig',
            '<script type="application/ld+json">{"@type":"FAQPage"}</script>'
        ));

        $warnings = $this->checker()->check(null, $page);

        static::assertCount(1, $warnings);
        static::assertSame(PageWarningChecker::WARNING_HANDWRITTEN_JSON_LD, $warnings[0]['code']);
        static::assertStringContainsString('moorl-twig', $warnings[0]['message']);
    }

    /**
     * Der Hinweis hängt nicht am Slot-Typ: Freies Markup gibt es auch im Text- und im HTML-Baustein.
     */
    public function testReportsHandwrittenJsonLdInAnySlotType(): void
    {
        $page = $this->makeCmsPage($this->makeMarkupSlot(
            'html',
            '<script type="application/ld+json">{"@type":"Product"}</script>'
        ));

        $warnings = $this->checker()->check(null, $page);

        static::assertCount(1, $warnings);
        static::assertStringContainsString('html', $warnings[0]['message']);
    }

    public function testSilentWhenNoSlotCarriesJsonLd(): void
    {
        $page = $this->makeCmsPage(
            $this->makeMarkupSlot('moorl-twig', '<div class="container">Nur Text</div>'),
            $this->makeSlot('text'),
        );

        static::assertSame([], $this->checker()->check(null, $page));
    }

    public function testReportsFaqPageWithoutFaqBlock(): void
    {
        $graph = ['@graph' => [['@type' => 'FAQPage', '@id' => 'https://shop.example/x#faq']]];
        $page = $this->makeCmsPage($this->makeSlot('text'));

        $warnings = $this->checker()->check($graph, $page);

        static::assertCount(1, $warnings);
        static::assertSame(PageWarningChecker::WARNING_FAQ_WITHOUT_BLOCK, $warnings[0]['code']);
    }

    public function testSilentWhenTheFaqPageComesFromAnFaqBlock(): void
    {
        $graph = ['@graph' => [['@type' => 'FAQPage', '@id' => 'https://shop.example/x#faq']]];
        $page = $this->makeCmsPage($this->makeSlot('rc-faq', [['question' => 'Wie breit?', 'answer' => '80 cm']]));

        static::assertSame([], $this->checker()->check($graph, $page));
    }

    /**
     * Ein Knoten darf laut JSON-LD mehrere Typen tragen — der Hinweis muss ihn trotzdem finden.
     */
    public function testFindsFaqPageInAListOfTypes(): void
    {
        $graph = ['@graph' => [['@type' => ['WebPage', 'FAQPage'], '@id' => 'https://shop.example/x']]];
        $page = $this->makeCmsPage($this->makeSlot('text'));

        $warnings = $this->checker()->check($graph, $page);

        static::assertCount(1, $warnings);
        static::assertSame(PageWarningChecker::WARNING_FAQ_WITHOUT_BLOCK, $warnings[0]['code']);
    }

    public function testReportsBothWarningsTogether(): void
    {
        $graph = ['@graph' => [['@type' => 'FAQPage', '@id' => 'https://shop.example/x#faq']]];
        $page = $this->makeCmsPage($this->makeMarkupSlot(
            'moorl-twig',
            '<script type="application/ld+json">{"@type":"FAQPage"}</script>'
        ));

        $codes = array_column($this->checker()->check($graph, $page), 'code');

        static::assertSame(
            [PageWarningChecker::WARNING_HANDWRITTEN_JSON_LD, PageWarningChecker::WARNING_FAQ_WITHOUT_BLOCK],
            $codes
        );
    }

    public function testSilentWithoutAPage(): void
    {
        static::assertSame([], $this->checker()->check(null, null));
    }

    private function checker(): PageWarningChecker
    {
        return new PageWarningChecker(new CmsSlotIterator());
    }

    /**
     * Slot mit freiem Markup. Gesetzt wird die rohe `config` — so kommt sie aus der Datenbank,
     * wenn der Slot ohne Storefront-Auflösung geladen wird, wie in der Diagnose.
     */
    private function makeMarkupSlot(string $type, string $markup): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('markup-slot-' . $type);
        $slot->setSlot('markup-slot-' . $type);
        $slot->setType($type);
        $slot->setConfig(['contentHTML' => ['source' => 'static', 'value' => $markup]]);
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('contentHTML', FieldConfig::SOURCE_STATIC, $markup),
        ]));

        return $slot;
    }
}
