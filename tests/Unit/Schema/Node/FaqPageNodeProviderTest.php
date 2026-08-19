<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\FaqPageNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class FaqPageNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    public function testNotSupportedWithoutCmsPage(): void
    {
        static::assertFalse($this->provider()->supports($this->makeContext()));
    }

    public function testBuildsFaqPageFromMultipleSlots(): void
    {
        $page = $this->makeCmsPage(
            $this->makeSlot('rc-faq', [['question' => 'Frage A', 'answer' => 'Antwort A']]),
            $this->makeSlot('text'),
            $this->makeSlot('rc-faq', [['question' => 'Frage B', 'answer' => 'Antwort B']]),
        );
        $context = $this->makeContext(pageUrl: 'https://shop.example/fittings', cmsPage: $page);

        static::assertTrue($this->provider()->supports($context));

        $node = $this->provider()->provide($context)[0];
        static::assertSame('FAQPage', $node['@type']);
        static::assertSame('https://shop.example/fittings#faq', $node['@id']);
        static::assertCount(2, $node['mainEntity']);
        static::assertSame('Question', $node['mainEntity'][0]['@type']);
        static::assertSame('Frage A', $node['mainEntity'][0]['name']);
        static::assertSame('Antwort A', $node['mainEntity'][0]['acceptedAnswer']['text']);
        static::assertSame('Frage B', $node['mainEntity'][1]['name']);
    }

    public function testBuildsFaqPageOnBlogPages(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('rc-faq', [['question' => 'Frage', 'answer' => 'Antwort']]));
        $context = $this->makeContext(
            pageType: \Ruhrcoder\RcStructuredData\Schema\SchemaContext::TYPE_BLOG,
            pageUrl: 'https://shop.example/blog/x',
            cmsPage: $page,
        );

        $node = $this->provider()->provide($context)[0];
        static::assertSame('FAQPage', $node['@type']);
        static::assertSame('https://shop.example/blog/x#faq', $node['@id']);
        static::assertCount(1, $node['mainEntity']);
    }

    public function testReturnsNothingWhenNoFaqSlots(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('text'), $this->makeSlot('youtube-video'));
        $context = $this->makeContext(cmsPage: $page);

        static::assertSame([], $this->provider()->provide($context));
    }

    public function testReturnsNothingWhenFaqSlotEmpty(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('rc-faq', []));
        $context = $this->makeContext(cmsPage: $page);

        static::assertSame([], $this->provider()->provide($context));
    }

    private function provider(): FaqPageNodeProvider
    {
        return new FaqPageNodeProvider(new CmsSlotIterator(), new FaqCmsBlockExtractor($this->passThroughSanitizer()), new IdFactory());
    }

    /**
     * Der Sanitizer gehört nicht zum Prüfgegenstand dieses Tests. Die Doublette reicht den
     * Text durch, damit die Zusicherungen den Knotenaufbau messen.
     */
    private function passThroughSanitizer(): HtmlSanitizer
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        return $sanitizer;
    }
}
