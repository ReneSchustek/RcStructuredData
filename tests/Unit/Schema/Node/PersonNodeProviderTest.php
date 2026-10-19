<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Schema\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Schema\Cms\CmsSlotIterator;
use Ruhrcoder\RcStructuredData\Schema\IdFactory;
use Ruhrcoder\RcStructuredData\Schema\Node\PersonNodeProvider;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\CmsFixtureTrait;
use Ruhrcoder\RcStructuredData\Tests\Unit\Schema\SchemaContextTrait;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;

/**
 * Prüft die Mitarbeiter aus den Personenkarten: nur Name und Funktion, verknüpft mit der Organisation.
 */
#[CoversClass(PersonNodeProvider::class)]
final class PersonNodeProviderTest extends TestCase
{
    use CmsFixtureTrait;
    use SchemaContextTrait;

    private int $peopleCounter = 0;

    /**
     * Was: Teamseite mit Personenkarten und einer Karte „Zentrale" ohne Porträts.
     * Warum: Nur Personen sollen als Person erscheinen, und nur mit Name und Funktion (Datenschutz).
     * Erwartet: zwei Personen mit jobTitle bis zum ersten „·", worksFor auf die Organisation, kein email/telephone/image.
     */
    public function testBuildsPeopleFromPortraitCards(): void
    {
        $page = $this->makeCmsPage(
            $this->peopleSlot(true, [
                ['title' => 'Anna Muster', 'text' => '<p>Vertrieb · Tel. 01234 5678</p>', 'media' => 'bild-1'],
                ['title' => 'Bert Beispiel', 'text' => 'Werkstattleitung'],
            ]),
            $this->peopleSlot(false, [['title' => 'Zentrale', 'text' => 'Mo–Do 8–17 Uhr']]),
        );
        $context = $this->makeContext(baseUrl: 'https://shop.example/', pageUrl: 'https://shop.example/team', cmsPage: $page);

        $nodes = $this->provider()->provide($context);

        static::assertCount(2, $nodes);
        static::assertSame('Person', $nodes[0]['@type']);
        static::assertSame('Anna Muster', $nodes[0]['name']);
        static::assertSame('Vertrieb', $nodes[0]['jobTitle']);
        static::assertSame(['@id' => 'https://shop.example/#organization'], $nodes[0]['worksFor']);
        static::assertSame('Werkstattleitung', $nodes[1]['jobTitle']);
        static::assertNotSame($nodes[0]['@id'], $nodes[1]['@id']);
        foreach (['email', 'telephone', 'image'] as $private) {
            static::assertArrayNotHasKey($private, $nodes[0]);
        }
    }

    /**
     * Was: Seite ohne Personenkarten.
     * Warum: Personen gehören nur auf die Seite, auf der sie stehen.
     * Erwartet: keine Knoten.
     */
    public function testNoPeopleWithoutPortraitCards(): void
    {
        $page = $this->makeCmsPage($this->makeSlot('text'), $this->peopleSlot(false, [['title' => 'Paketversand']]));

        static::assertSame([], $this->provider()->provide($this->makeContext(cmsPage: $page)));
    }

    private function provider(): PersonNodeProvider
    {
        return new PersonNodeProvider(new CmsSlotIterator(), new IdFactory());
    }

    /**
     * @param list<array<string, string>> $items
     */
    private function peopleSlot(bool $portraits, array $items): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $id = 'people-' . ++$this->peopleCounter;
        $slot->setUniqueIdentifier($id);
        $slot->setSlot($id);
        $slot->setType('rc-tr-service');

        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('display', FieldConfig::SOURCE_STATIC, 'cards'));
        $config->add(new FieldConfig('portraits', FieldConfig::SOURCE_STATIC, $portraits));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $items));
        $slot->setFieldConfig($config);

        return $slot;
    }
}
