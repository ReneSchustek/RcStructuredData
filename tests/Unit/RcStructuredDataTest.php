<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use Shopware\Core\Framework\Plugin;

/**
 * Sicherstellung, dass die Plugin-Basisklasse korrekt aufgebaut ist.
 *
 * Das Plugin ist reine Administrations-Erweiterung ohne PHP-Geschäftslogik, daher
 * prüfen die Tests nur den Kontrakt der Bundle-/Plugin-Basis: Die Klasse muss
 * instanziierbar sein und sich als Shopware-Plugin verhalten — sonst würde Shopware
 * sie beim Laden des Plugin-Lifecycles gar nicht erst akzeptieren.
 */
class RcStructuredDataTest extends TestCase
{
    /**
     * Was: Instanziiert das Plugin und prüft die Vererbung.
     * Warum: Ein falscher Basistyp würde den gesamten Plugin-Lifecycle (install,
     *        activate, Asset-Build) brechen.
     * Erwartet: Die Instanz ist ein Shopware\Core\Framework\Plugin.
     */
    public function testIsShopwarePlugin(): void
    {
        $plugin = new RcStructuredData(true, __DIR__);

        static::assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Was: Liest den Aktiv-Zustand über den Konstruktor-Parameter zurück.
     * Warum: Shopware übergibt diesen Flag beim Boot; ein abweichendes Verhalten
     *        würde anzeigen, dass der Basis-Konstruktor nicht korrekt durchläuft.
     * Erwartet: isActive() spiegelt den übergebenen Wert.
     */
    public function testReportsActiveState(): void
    {
        $active = new RcStructuredData(true, __DIR__);
        $inactive = new RcStructuredData(false, __DIR__);

        static::assertTrue($active->isActive());
        static::assertFalse($inactive->isActive());
    }
}
