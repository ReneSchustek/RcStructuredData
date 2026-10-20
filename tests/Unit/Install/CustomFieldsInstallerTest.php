<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\Install\CustomFieldsInstaller;
use Ruhrcoder\RcStructuredData\Tests\Unit\StaticRepositoryTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Prüft, dass Installation und Aktualisierung den Feldsatz „rc_schema“ fortschreiben statt ihn doppelt anzulegen, und
 * dass die Deinstallation ihn entfernt.
 */
#[CoversClass(CustomFieldsInstaller::class)]
final class CustomFieldsInstallerTest extends TestCase
{
    use StaticRepositoryTrait;

    private const SET_ID = '0190aa000000000000000000000000f1';
    private const FIELD_ID = '0190aa000000000000000000000000f2';
    private const RELATION_ID = '0190aa000000000000000000000000f3';

    /**
     * Was: Erstinstallation ohne vorhandenen Feldsatz und ohne vorhandene Felder.
     * Warum: Ohne Kennungen legt der Upsert alles neu an.
     * Erwartet: ein Upsert des Feldsatzes ohne `id`, sechs Felder ohne `id`.
     */
    public function testAFreshInstallCreatesTheSetAndItsFields(): void
    {
        $sets = self::repository(CustomFieldSetCollection::class, [new CustomFieldSetCollection()]);

        $this->installer(
            $sets,
            fields: self::repository(
                CustomFieldCollection::class,
                [
                    new CustomFieldCollection(),
                ]
            )
        )->install(
            Context::createDefaultContext()
        );

        $payload = $sets->upserts[0][0];
        self::assertSame('rc_schema', $payload['name']);
        self::assertArrayNotHasKey('id', $payload);
        self::assertCount(6, $payload['customFields']);
        self::assertSame([], array_filter(array_column($payload['customFields'], 'id')));
    }

    /**
     * Was: Aktualisierung mit vorhandenem Feldsatz und einem vorhandenen Feld.
     * Warum: Ohne die vorhandenen Kennungen scheiterte der Upsert an der Eindeutigkeit des Namens.
     * Erwartet: Feldsatz und das vorhandene Feld tragen ihre Kennung, die übrigen Felder nicht.
     */
    public function testAnUpdateKeepsExistingIds(): void
    {
        $sets = self::repository(CustomFieldSetCollection::class, [new CustomFieldSetCollection([$this->set()])]);
        $field = new CustomFieldEntity();
        $field->setId(self::FIELD_ID);
        $field->setName('rc_schema_description');

        $this->installer(
            $sets,
            fields: self::repository(
                CustomFieldCollection::class,
                [
                    new CustomFieldCollection([$field]),
                ]
            )
        )->install(Context::createDefaultContext());

        $payload = $sets->upserts[0][0];
        self::assertSame(self::SET_ID, $payload['id']);
        $ids = array_column($payload['customFields'], 'id', 'name');
        self::assertSame(['rc_schema_description' => self::FIELD_ID], $ids);
    }

    /**
     * Was: Zuordnung zu Kategorie und Landingpage, eine davon besteht schon.
     * Warum: Wiederholtes Aktivieren darf keine doppelten Zuordnungen anlegen.
     * Erwartet: zwei Zuordnungen, die vorhandene mit ihrer Kennung.
     */
    public function testRelationsReuseExistingOnes(): void
    {
        $relation = new CustomFieldSetRelationEntity();
        $relation->setId(self::RELATION_ID);
        $relation->setEntityName('category');
        $set = $this->set();
        $set->setRelations(new CustomFieldSetRelationCollection([$relation]));
        $relations = self::repository(CustomFieldSetRelationCollection::class);

        $this->installer(
            self::repository(
                CustomFieldSetCollection::class,
                [
                    new CustomFieldSetCollection([$set]),
                ]
            ),
            $relations
        )->addRelations(
            Context::createDefaultContext()
        );

        self::assertSame([
            ['customFieldSetId' => self::SET_ID, 'entityName' => 'category', 'id' => self::RELATION_ID],
            ['customFieldSetId' => self::SET_ID, 'entityName' => 'landing_page'],
        ], $relations->upserts[0]);
    }

    /**
     * Was: Zuordnung ohne Feldsatz, Feldsatz ohne geladene Zuordnungen.
     * Warum: Ohne Feldsatz gibt es nichts zuzuordnen; fehlende Zuordnungen heißen „neu anlegen“.
     * Erwartet: im ersten Fall kein Schreiben, im zweiten beide Zuordnungen ohne Kennung.
     */
    public function testRelationsWithoutSetOrWithoutLoadedRelations(): void
    {
        $relations = self::repository(CustomFieldSetRelationCollection::class);
        $installer = $this->installer(
            self::repository(
                CustomFieldSetCollection::class,
                [
                    new CustomFieldSetCollection(),
                    new CustomFieldSetCollection([$this->set()]),
                ]
            ),
            $relations
        );

        $installer->addRelations(Context::createDefaultContext());
        self::assertCount(0, $relations->upserts);

        $installer->addRelations(Context::createDefaultContext());
        self::assertSame(['category', 'landing_page'], array_column($relations->upserts[0], 'entityName'));
        self::assertSame([], array_filter(array_column($relations->upserts[0], 'id')));
    }

    /**
     * Was: Deinstallation mit und ohne vorhandenen Feldsatz.
     * Warum: Der Feldsatz gehört dem Plugin und geht mit ihm; ohne Feldsatz bleibt nichts zu löschen.
     * Erwartet: einmal Löschen des Feldsatzes, einmal nichts.
     */
    public function testUninstallRemovesTheSet(): void
    {
        $sets = self::repository(CustomFieldSetCollection::class, [
new IdSearchResult(
    1,
    [
        self::SET_ID => [
            'primaryKey' => self::SET_ID,
            'data' => [],
        ],
    ],
    new Criteria(),
    Context::createDefaultContext(),
),
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        ]);
        $installer = $this->installer($sets);

        $installer->uninstall(Context::createDefaultContext());
        $installer->uninstall(Context::createDefaultContext());

        self::assertSame([[['id' => self::SET_ID]]], $sets->deletes);
    }

    /**
     * @param StaticEntityRepository<CustomFieldSetCollection>              $sets
     * @param StaticEntityRepository<CustomFieldSetRelationCollection>|null $relations
     * @param StaticEntityRepository<CustomFieldCollection>|null            $fields
     */
    private function installer(
        StaticEntityRepository $sets,
        ?StaticEntityRepository $relations = null,
        ?StaticEntityRepository $fields = null,
    ): CustomFieldsInstaller {
        return new CustomFieldsInstaller(
            $sets,
            $relations ?? self::repository(CustomFieldSetRelationCollection::class),
            $fields ?? self::repository(CustomFieldCollection::class)
        );
    }

    private function set(): CustomFieldSetEntity
    {
        $set = new CustomFieldSetEntity();
        $set->setId(self::SET_ID);
        $set->setName('rc_schema');

        return $set;
    }
}
