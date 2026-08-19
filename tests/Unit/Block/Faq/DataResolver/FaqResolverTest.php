<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Block\Faq\DataResolver;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Block\Faq\DataResolver\FaqResolver;
use Ruhrcoder\RcStructuredData\Block\Faq\Struct\FaqStruct;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class FaqResolverTest extends TestCase
{
    use CmsFixtureTrait;

    public function testRegistersRcFaqType(): void
    {
        static::assertSame('rc-faq', $this->resolver()->getType());
        static::assertNull($this->resolver()->collect(
            $this->makeSlot('rc-faq'),
            $this->createMock(ResolverContext::class),
        ));
    }

    public function testEnrichSetsFaqStructFromSlot(): void
    {
        $resolver = $this->resolver();
        $slot = $this->makeSlot('rc-faq', [
            ['question' => 'Frage', 'answer' => 'Antwort'],
            ['question' => '', 'answer' => 'wird verworfen'],
        ]);

        $resolver->enrich(
            $slot,
            $this->createMock(ResolverContext::class),
            $this->createMock(ElementDataCollection::class),
        );

        $data = $slot->getData();
        static::assertInstanceOf(FaqStruct::class, $data);
        static::assertSame([['question' => 'Frage', 'answer' => 'Antwort']], $data->getItems());
        static::assertSame('accordion', $data->getDisplayMode());
    }

    public function testEnrichReadsDisplayMode(): void
    {
        $slot = $this->makeSlot('rc-faq', [['question' => 'Q', 'answer' => 'A']], 'list');

        $this->resolver()->enrich($slot, $this->createMock(ResolverContext::class), $this->createMock(ElementDataCollection::class));

        $data = $slot->getData();
        static::assertInstanceOf(FaqStruct::class, $data);
        static::assertSame('list', $data->getDisplayMode());
    }

    public function testEnrichFallsBackToAccordionForUnknownDisplayMode(): void
    {
        $slot = $this->makeSlot('rc-faq', [['question' => 'Q', 'answer' => 'A']], 'carousel');

        $this->resolver()->enrich($slot, $this->createMock(ResolverContext::class), $this->createMock(ElementDataCollection::class));

        $data = $slot->getData();
        static::assertInstanceOf(FaqStruct::class, $data);
        static::assertSame('accordion', $data->getDisplayMode());
    }

    private function resolver(): FaqResolver
    {
        return new FaqResolver(new FaqCmsBlockExtractor($this->passThroughSanitizer()));
    }

    /**
     * Der Sanitizer ist hier nicht der Prüfgegenstand — was er entfernt, ist im Kern geprüft
     * und im Test des Extractors festgehalten. Diese Doublette reicht den Text durch, damit
     * die Zusicherungen dieses Tests den Resolver messen und nicht den Sanitizer.
     */
    private function passThroughSanitizer(): HtmlSanitizer
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        return $sanitizer;
    }
}
