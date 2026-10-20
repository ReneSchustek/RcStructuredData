<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcStructuredData\RcStructuredData;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\Container;

/**
 * Prüft, dass jeder Schritt des Lebenszyklus den Feldsatz `rc_schema` pflegt und die Deinstallation ihn nur entfernt,
 * wenn der Händler die Daten nicht behalten will.
 */
#[CoversClass(RcStructuredData::class)]
final class RcStructuredDataLifecycleTest extends TestCase
{
    use StaticRepositoryTrait;

    private const SET_ID = '0190aa000000000000000000000000f1';

    /**
     * Was: Installation, Aktivierung und Aktualisierung.
     * Warum: Die Installation legt den Feldsatz an, die Aktivierung ordnet ihn zu, die Aktualisierung ergänzt neue
     *        Felder und Zuordnungen auf bestehenden Shops.
     * Erwartet: zwei Upserts des Feldsatzes und zwei der Zuordnungen.
     */
    public function testInstallActivateAndUpdateMaintainTheFieldSet(): void
    {
        $set = new CustomFieldSetEntity();
        $set->setId(self::SET_ID);
        $sets = self::repository(CustomFieldSetCollection::class, [
            new CustomFieldSetCollection(),
            new CustomFieldSetCollection([$set]),
            new CustomFieldSetCollection([$set]),
            new CustomFieldSetCollection([$set]),
        ]);
        $relations = self::repository(CustomFieldSetRelationCollection::class);
        $fields = self::repository(
            CustomFieldCollection::class,
            [
                new CustomFieldCollection(),
                new CustomFieldCollection(),
            ]
        );
        $plugin = $this->plugin($sets, $relations, $fields);
        $context = Context::createDefaultContext();

        $plugin->install($this->context(InstallContext::class, $context));
        $plugin->activate($this->context(ActivateContext::class, $context));
        $plugin->update($this->context(UpdateContext::class, $context));

        self::assertCount(2, $sets->upserts);
        self::assertCount(2, $relations->upserts);
    }

    /**
     * Was: Deinstallation mit und ohne „Daten behalten“.
     * Warum: Wer die Daten behält, will die gepflegten Felder nach einer Neuinstallation wiederfinden.
     * Erwartet: nur ohne „Daten behalten“ wird der Feldsatz gelöscht.
     */
    public function testUninstallRemovesTheFieldSetOnlyWithoutKeepingData(): void
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
        ]);
        $plugin = $this->plugin(
            $sets,
            self::repository(CustomFieldSetRelationCollection::class),
            self::repository(CustomFieldCollection::class)
        );

        $plugin->uninstall($this->uninstallContext(keepUserData: true));
        self::assertCount(0, $sets->deletes);

        $plugin->uninstall($this->uninstallContext(keepUserData: false));
        self::assertSame([[['id' => self::SET_ID]]], $sets->deletes);
    }

    public function testFailsClearlyWithoutContainer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Plugin container is not available.');

        (new RcStructuredData(
            true,
            __DIR__
        ))->install(
            $this->context(
                InstallContext::class,
                Context::createDefaultContext()
            )
        );
    }

    /**
     * @param StaticEntityRepository<CustomFieldSetCollection>         $sets
     * @param StaticEntityRepository<CustomFieldSetRelationCollection> $relations
     * @param StaticEntityRepository<CustomFieldCollection>            $fields
     */
    private function plugin(
        StaticEntityRepository $sets,
        StaticEntityRepository $relations,
        StaticEntityRepository $fields,
    ): RcStructuredData {
        $container = new Container();
        $container->set('custom_field_set.repository', $sets);
        $container->set('custom_field_set_relation.repository', $relations);
        $container->set('custom_field.repository', $fields);

        $plugin = new RcStructuredData(true, __DIR__);
        $plugin->setContainer($container);

        return $plugin;
    }

    /**
     * @template T of InstallContext
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function context(string $class, Context $context): InstallContext
    {
        $lifecycle = $this->createMock($class);
        $lifecycle->method('getContext')->willReturn($context);

        return $lifecycle;
    }

    private function uninstallContext(bool $keepUserData): UninstallContext
    {
        $lifecycle = $this->createMock(UninstallContext::class);
        $lifecycle->method('getContext')->willReturn(Context::createDefaultContext());
        $lifecycle->method('keepUserData')->willReturn($keepUserData);

        return $lifecycle;
    }
}
