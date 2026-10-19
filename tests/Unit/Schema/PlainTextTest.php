<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\PlainText;
use Ruhrcoder\RcStructuredData\Schema\PluginConfigReader;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Prüft die beiden Helfer, die vorher in mehreren Klassen je eine eigene Fassung hatten: Klartext aus HTML und das
 * Lesen der Plugin-Einstellungen.
 */
#[CoversClass(PlainText::class)]
#[CoversClass(PluginConfigReader::class)]
final class PlainTextTest extends TestCase
{
    /**
     * Was: HTML mit Absätzen, Zeilenumbruch und Entitäten.
     * Warum: Ein `<p>` oder `&amp;` stünde sonst sichtbar im Suchergebnis, und „Satz eins.Satz zwei." klebte zusammen.
     * Erwartet: ein Satz Klartext mit Leerzeichen zwischen den Blöcken.
     */
    public function testHtmlBecomesPlainText(): void
    {
        self::assertSame(
            'Satz eins. Satz zwei & drei. Zeile',
            PlainText::fromHtml("<p>Satz eins.</p><p>Satz zwei &amp; drei.</p>\n<br>Zeile"),
        );
        self::assertSame('', PlainText::fromHtml(null));
        self::assertSame('', PlainText::fromHtml(''));
    }

    public function testFirstFilledSkipsEmptyAndNonText(): void
    {
        self::assertSame('Titel', PlainText::firstFilled(null, '  ', 42, ' Titel '));
        self::assertSame('', PlainText::firstFilled(null, ''));
    }

    /**
     * Was: Name der Organisation leer, Shop-Name gesetzt; Zeilenliste mit leeren Zeilen.
     * Erwartet: Rückfall auf den Shop-Namen, Zeilen ohne Leerzeilen und getrimmt.
     */
    public function testConfigReader(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getString')->willReturnCallback(static fn (string $key): string => match ($key) {
            'RcStructuredData.config.organizationName' => ' ',
            'core.basicInformation.shopName' => ' Trummer Edelstahl ',
            'RcStructuredData.config.organizationSameAs' => "https://a.example\r\n\n  https://b.example  ",
            default => '',
        });
        $reader = new PluginConfigReader($config);

        self::assertSame('Trummer Edelstahl', $reader->organizationName('sc'));
        self::assertSame(['https://a.example', 'https://b.example'], $reader->lines('organizationSameAs', 'sc'));
        self::assertSame([], $reader->lines('fehlt', 'sc'));
    }
}
