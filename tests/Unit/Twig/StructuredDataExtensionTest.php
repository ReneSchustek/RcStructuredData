<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Twig\StructuredDataExtension;
use Shopware\Core\Framework\Feature;

/**
 * Die drei Zustände, die es geben kann — und der dritte ist der Grund für diese Klasse.
 */
#[CoversClass(StructuredDataExtension::class)]
final class StructuredDataExtensionTest extends TestCase
{
    private StructuredDataExtension $extension;

    /** @var array<string, array<string, mixed>> */
    private array $registeredBefore = [];

    /**
     * `Feature` hält seine Registrierung statisch, und `registerFeatures()` fügt hinzu statt zu
     * ersetzen. Ohne Zurücksetzen sähe der Test „Flag ist weg" das Flag des vorigen Tests — er
     * war beim ersten Lauf genau daran gescheitert. Der Ausgangszustand wird danach
     * wiederhergestellt, damit die übrigen Tests der Reihe nichts davon merken.
     */
    protected function setUp(): void
    {
        $this->extension = new StructuredDataExtension();
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
        $namen = array_map(
            static fn ($function): string => $function->getName(),
            $this->extension->getFunctions(),
        );

        self::assertContains('rc_schema_core_renders_global_json_ld', $namen);
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
     * Der eigentliche Zweck: Ab Shopware 6.8 ist das Flag entfernt. Die frühere Abfrage
     * `not feature('JSON_LD_DATA')` hätte sich hier ins Gegenteil verkehrt, weil
     * `Feature::isActive()` für ein unbekanntes Flag `false` liefert — WebSite und
     * Organization wären doppelt im Kopfbereich gelandet.
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
}
