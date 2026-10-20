<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Extractor\FaqCmsBlockExtractor;
use Ruhrcoder\RcStructuredData\Schema\OrganizationDetails;
use Ruhrcoder\RcStructuredData\Schema\Product\ProductOfferDetails;
use Ruhrcoder\RcStructuredData\Schema\Product\ShippingDetailsProvider;
use Ruhrcoder\RcStructuredData\Twig\StructuredDataExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Die drei Zustände, die es geben kann, und der dritte ist der Grund für diese Klasse.
 */
#[CoversClass(StructuredDataExtension::class)]
final class StructuredDataExtensionTest extends TestCase
{
    private StructuredDataExtension $extension;

    /** @var array<string, array<string, mixed>> */
    private array $registeredBefore = [];

    /**
     * `Feature` hält seine Registrierung statisch, und `registerFeatures()` fügt hinzu statt zu
     * ersetzen. Ohne Zurücksetzen sähe der Test „Flag ist weg" das Flag des vorigen Tests und
     * schlüge fehl. Der Ausgangszustand wird danach
     * wiederhergestellt, damit die übrigen Tests der Reihe nichts davon merken.
     */
    protected function setUp(): void
    {
        $sanitizer = $this->createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnCallback(
            static fn (
                string $text,
            ): string => strip_tags($text, '<p><strong>')
        );
        $this->extension = new StructuredDataExtension(
            $this->createMock(ShippingDetailsProvider::class),
            new FaqCmsBlockExtractor($sanitizer),
            new OrganizationDetails($this->createStub(SystemConfigService::class)),
            new ProductOfferDetails(),
        );
        $this->registeredBefore = Feature::getRegisteredFeatures();
        Feature::resetRegisteredFeatures();
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->registeredBefore);
    }

    public function testFunctionIsRegisteredForTemplates(): void
    {
        $names = array_map(
            static fn ($function): string => $function->getName(),
            $this->extension->getFunctions(),
        );

        self::assertContains('rc_schema_core_renders_global_json_ld', $names);
        self::assertContains('rc_schema_faq_page', $names);
        self::assertContains('rc_schema_return_policy', $names);
    }

    public function testCoreDoesNotRenderWhileTheFlagExistsAndIsOff(): void
    {
        Feature::registerFeatures(['JSON_LD_DATA' => ['name' => 'JSON_LD_DATA', 'default' => false]]);
        Feature::setActive('JSON_LD_DATA', false);

        self::assertFalse(
            $this->extension->coreRendersGlobalJsonLd(),
            'Bei abgeschaltetem Flag gibt der Kern nichts aus — das Plugin muss einspringen.',
        );
    }

    public function testCoreRendersWhileTheFlagExistsAndIsOn(): void
    {
        Feature::registerFeatures(['JSON_LD_DATA' => ['name' => 'JSON_LD_DATA', 'default' => false]]);
        Feature::setActive('JSON_LD_DATA', true);

        self::assertTrue(
            $this->extension->coreRendersGlobalJsonLd(),
            'Bei eingeschaltetem Flag gibt der Kern aus — das Plugin muss sich zurückhalten.',
        );
    }

    /**
     * In Shopware 6.8 fehlt das Flag. Eine Abfrage `not feature('JSON_LD_DATA')` verkehrte
     * sich hier ins Gegenteil, weil `Feature::isActive()` für ein unbekanntes Flag `false`
     * liefert, und WebSite und Organization stünden doppelt im Kopfbereich.
     */
    public function testCoreRendersWhenTheFlagIsGone(): void
    {
        Feature::registerFeatures(['SOMETHING_ELSE' => ['name' => 'SOMETHING_ELSE', 'default' => false]]);

        self::assertFalse(Feature::has('JSON_LD_DATA'), 'Vorbedingung: Das Flag ist nicht registriert.');
        self::assertTrue(
            $this->extension->coreRendersGlobalJsonLd(),
            'Ohne Flag gibt der Kern die strukturierten Daten fest aus.',
        );
    }

    /**
     * Was: Die Fragen der Produktseite, eine davon ohne Antwort, eine Antwort mit Skript.
     * Warum: Markup und sichtbarer Text müssen übereinstimmen; ein unvollständiges Paar zeigt das Theme nicht, und eine
     *        Antwort darf nie Skript ins JSON-LD tragen.
     * Erwartet: eine FAQPage mit dem einen vollständigen Paar, gereinigt, `@id` mit `#faq`.
     */
    public function testTheFaqPageCarriesOnlyCompleteCleanedPairs(): void
    {
        $page = $this->extension->faqPage([
            ['question' => ' Wie wird geklebt? ', 'answer' => '<p>Mit <strong>Druck</strong>.</p><script>x()</script>'],
            ['question' => 'Ohne Antwort?', 'answer' => ''],
        ], 'https://shop.example/kleber/2k-362');

        self::assertNotNull($page);
        self::assertSame('FAQPage', $page['@type']);
        self::assertSame('https://shop.example/kleber/2k-362#faq', $page['@id']);
        self::assertSame([[
            '@type' => 'Question',
            'name' => 'Wie wird geklebt?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => '<p>Mit <strong>Druck</strong>.</p>x()'],
        ]], $page['mainEntity']);
    }

    /**
     * Was: Keine Fragen, keine Erweiterung, Unsinn.
     * Erwartet: keine FAQPage.
     */
    public function testWithoutQuestionsThereIsNoFaqPage(): void
    {
        self::assertNull($this->extension->faqPage([], 'https://shop.example/a'));
        self::assertNull($this->extension->faqPage(null, 'https://shop.example/a'));
        self::assertNull($this->extension->faqPage('kein Feld', 'https://shop.example/a'));
    }

    /**
     * Was: Ein JSON-LD-Block mit Bildadressen mit Leerzeichen, einer schon kodierten und einer fremden Adresse.
     * Warum: Die Kernvorlagen übernehmen `media.url` roh; im Markup stünde ein Leerzeichen sonst unkodiert.
     * Erwartet: Medienadressen kodiert, die kodierte unverändert, andere Werte unberührt.
     */
    public function testMediaUrlsAreEncodedEverywhereInTheBlock(): void
    {
        $data = $this->extension->encodeMediaUrls([
'image' => [
    'https://shop.example/media/bd/ab/CN1 - CN2.jpg?ts=1',
    'https://shop.example/thumbnail/bd/CN1%20-%20CN2_200x175.jpg',
],
            'primaryImageOfPage' => 'https://shop.example/media/a/b c.jpg',
            'name' => 'Abstandhalter / media / Edelstahl',
            'url' => 'https://shop.example/abstandhalter/cn1',
            'offers' => ['price' => 9.66],
        ]);

        self::assertSame(
            [
                'https://shop.example/media/bd/ab/CN1%20-%20CN2.jpg?ts=1',
                'https://shop.example/thumbnail/bd/CN1%20-%20CN2_200x175.jpg',
            ],
            $data['image']
        );
        self::assertSame('https://shop.example/media/a/b%20c.jpg', $data['primaryImageOfPage']);
        self::assertSame('Abstandhalter / media / Edelstahl', $data['name']);
        self::assertSame('https://shop.example/abstandhalter/cn1', $data['url']);
        self::assertSame(['price' => 9.66], $data['offers']);
    }
}
