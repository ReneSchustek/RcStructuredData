<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nagelt die JSON-LD-Injektionsstellen gegen die Phantom-Block-Klasse fest: Ein `sw_extends`-Block,
 * den es im Ziel-Template des Cores nicht (mehr) gibt, wird von Twig stillschweigend ignoriert — der
 * gesamte strukturierte-Daten-`@graph` würde dann nie ausgegeben, ohne Fehler. Diese Tests pinnen
 * Ziel-Template und Blockname der drei Meta-Overrides gegen einen Core-Rename.
 */
final class TwigContractTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../../src/Resources/views/storefront';

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> [relativer Pfad, sw_extends-Ziel, Blockname]
     */
    public static function overrideProvider(): array
    {
        return [
            'layout global' => [
                'layout/meta.html.twig',
                "{% sw_extends '@Storefront/storefront/layout/meta.html.twig' %}",
                '{% block layout_head_json_ld_global %}',
            ],
            'content page' => [
                'page/content/meta.html.twig',
                "{% sw_extends '@Storefront/storefront/page/content/meta.html.twig' %}",
                '{% block layout_head_json_ld %}',
            ],
            'product detail' => [
                'page/product-detail/meta.html.twig',
                "{% sw_extends '@Storefront/storefront/page/product-detail/meta.html.twig' %}",
                '{% block layout_head_json_ld %}',
            ],
        ];
    }

    #[DataProvider('overrideProvider')]
    public function testOverrideExtendsCoreAndTargetsRealBlock(string $relativePath, string $extends, string $block): void
    {
        $content = (string) file_get_contents(self::VIEWS . '/' . $relativePath);

        self::assertStringContainsString($extends, $content, $relativePath);
        self::assertStringContainsString($block, $content, $relativePath);
    }

    public function testGraphIsJsonEncodedWithScriptSafeFlags(): void
    {
        // Der Graph landet in einem <script>-Kontext; HEX_TAG/HEX_AMP verhindern einen </script>-Breakout.
        $content = (string) file_get_contents(self::VIEWS . '/page/content/meta.html.twig');

        self::assertStringContainsString('|json_encode(', $content);
        self::assertStringContainsString('JSON_HEX_TAG', $content);
        self::assertStringContainsString('JSON_HEX_AMP', $content);
    }
}
