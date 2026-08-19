<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Extractor;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class FaqCmsBlockExtractorTest extends TestCase
{
    use CmsFixtureTrait;

    public function testSupportsOnlyRcFaqSlots(): void
    {
        $extractor = $this->extractor();

        static::assertTrue($extractor->supports($this->makeSlot('rc-faq', [])));
        static::assertFalse($extractor->supports($this->makeSlot('text')));
    }

    public function testExtractsValidPairsAndTrims(): void
    {
        $extractor = $this->extractor();
        $slot = $this->makeSlot('rc-faq', [
            ['question' => '  Frage 1  ', 'answer' => 'Antwort 1'],
            ['question' => 'Frage 2', 'answer' => 'Antwort 2'],
        ]);

        static::assertSame([
            ['question' => 'Frage 1', 'answer' => 'Antwort 1'],
            ['question' => 'Frage 2', 'answer' => 'Antwort 2'],
        ], $extractor->extract($slot));
    }

    public function testSkipsIncompletePairs(): void
    {
        $extractor = $this->extractor();
        $slot = $this->makeSlot('rc-faq', [
            ['question' => 'Frage', 'answer' => ''],
            ['question' => '', 'answer' => 'Antwort'],
            ['question' => 'Gültig', 'answer' => 'Ja'],
            ['answer' => 'ohne Frage-Key'],
        ]);

        static::assertSame([
            ['question' => 'Gültig', 'answer' => 'Ja'],
        ], $extractor->extract($slot));
    }

    public function testReturnsEmptyWhenConfigMissing(): void
    {
        $extractor = $this->extractor();

        static::assertSame([], $extractor->extract($this->makeSlot('rc-faq')));
    }

    /**
     * Was: Die Antwort läuft durch Shopwares Sanitizer.
     * Warum: Sie wird in der Storefront mit `|raw` ausgegeben, damit die Formatierung aus dem
     *        Editor erhalten bleibt. Ohne Sanitizer wird aus einem Redakteursrecht ein
     *        Vollzugriff: Eingeschleustes Javascript liefe in der Sitzung jedes Besuchers,
     *        auch in der eines Administrators, der die Seite ansieht.
     *
     * Geprüft wird die Weitergabe, nicht das Ergebnis — was entfernt wird, entscheidet der
     * Kern und ist dort geprüft.
     */
    public function testTheAnswerIsPassedThroughTheSanitizer(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->expects(static::once())
            ->method('sanitize')
            ->with('<p>Antwort</p><script>alert(1)</script>')
            ->willReturn('<p>Antwort</p>');

        $extractor = new FaqCmsBlockExtractor($sanitizer);
        $slot = $this->makeSlot('rc-faq', [
            ['question' => 'Frage', 'answer' => '<p>Antwort</p><script>alert(1)</script>'],
        ]);

        static::assertSame(
            [['question' => 'Frage', 'answer' => '<p>Antwort</p>']],
            $extractor->extract($slot),
        );
    }

    /**
     * Die Gegenprobe: Die Frage wird **nicht** durch den Sanitizer geschickt.
     *
     * Sie wird in der Storefront escaped ausgegeben und ist damit ungefährlich. Ginge sie
     * durch den Sanitizer, stünden erlaubte Auszeichnungen hinterher als sichtbarer Text auf
     * der Seite — eine Verschlechterung ohne Sicherheitsgewinn.
     */
    public function testTheQuestionIsLeftUntouched(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnCallback(
            static fn (string $text): string => str_replace('<b>', '', $text),
        );

        $extractor = new FaqCmsBlockExtractor($sanitizer);
        $slot = $this->makeSlot('rc-faq', [
            ['question' => 'Frage mit <b>Auszeichnung', 'answer' => 'Antwort'],
        ]);

        $items = $extractor->extract($slot);

        static::assertSame('Frage mit <b>Auszeichnung', $items[0]['question']);
    }

    private function extractor(): FaqCmsBlockExtractor
    {
        $sanitizer = $this->createMock(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        return new FaqCmsBlockExtractor($sanitizer);
    }
}
