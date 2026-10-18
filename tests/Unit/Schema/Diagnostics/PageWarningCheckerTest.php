<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Diagnostics;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\PageWarningChecker;
use Ruhrcoder\RcStructuredData\Schema\Extractor\YoutubeVideoExtractor;
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
     * Ein Knoten darf laut JSON-LD mehrere Typen tragen, der Hinweis muss ihn trotzdem finden.
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

    /**
     * Was: Ein YouTube-Baustein, für den im Graphen kein Knoten steht.
     * Warum: Das Plugin lässt ein Video ohne
     *        Veröffentlichungsdatum aus. Ohne diese Meldung wäre das ein stiller Verlust:
     *        Niemand im Backoffice sähe, welches Video ein Datum braucht.
     */
    public function testReportsAVideoThatWasLeftOut(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('ypdWNKMB9ec'));

        $warnings = $this->checker()->check(['@graph' => []], $page);

        static::assertCount(1, $warnings);
        static::assertSame(PageWarningChecker::WARNING_VIDEO_WITHOUT_UPLOAD_DATE, $warnings[0]['code']);
        static::assertStringContainsString('ypdWNKMB9ec', $warnings[0]['message']);
    }

    /**
     * Die Gegenprobe: Steht der Knoten im Graphen, gibt es nichts zu melden.
     */
    public function testSilentWhenTheVideoIsInTheGraph(): void
    {
        $page = $this->makeCmsPage($this->makeYoutubeSlot('ypdWNKMB9ec'));

        $warnings = $this->checker()->check([
            '@graph' => [[
                '@type' => 'VideoObject',
                'contentUrl' => 'https://www.youtube.com/watch?v=ypdWNKMB9ec',
                'embedUrl' => 'https://www.youtube.com/embed/ypdWNKMB9ec',
            ]],
        ], $page);

        static::assertSame([], $warnings);
    }

    /**
     * Zwei ausgelassene Videos stehen in einer Meldung; eine je Video wäre im Backoffice
     * eine Wand aus Hinweisen.
     */
    public function testItNamesEveryLeftOutVideoInOneWarning(): void
    {
        $page = $this->makeCmsPage(
            $this->makeYoutubeSlot('ypdWNKMB9ec'),
            $this->makeYoutubeSlot('TQy29xs_nAM'),
        );

        $warnings = $this->checker()->check(['@graph' => []], $page);

        static::assertCount(1, $warnings);
        static::assertStringContainsString('ypdWNKMB9ec', $warnings[0]['message']);
        static::assertStringContainsString('TQy29xs_nAM', $warnings[0]['message']);
    }

    private function checker(): PageWarningChecker
    {
        return new PageWarningChecker(new CmsSlotIterator(), new YoutubeVideoExtractor());
    }

    /**
     * Slot mit freiem Markup. Gesetzt wird die rohe `config`, denn so kommt sie aus der Datenbank,
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
