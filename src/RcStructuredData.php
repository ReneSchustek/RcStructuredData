<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData;

use Ruhrcoder\RcStructuredData\Install\CustomFieldsInstaller;
use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Werkl\OpenBlogware\Page\Blog\BlogPageLoadedEvent;

/**
 * Basis-Plugin-Klasse der Schema.org-CMS-Erweiterung.
 *
 * Seit v2.0.0 ist das Plugin ein Schema Graph Builder: Es erzeugt auf Kategorie- und
 * Landingpages im Storefront einen zusammenhängenden JSON-LD-`@graph` (Organization,
 * WebSite, CollectionPage/WebPage, BreadcrumbList, später FAQPage/VideoObject), verknüpft
 * über `@id`. Die Daten werden zentral im Plugin gepflegt (Konfiguration + Category-Custom-
 * Fields), nicht als rohes JSON im CMS-HTML.
 *
 * Der Lifecycle legt nur das Custom-Field-Set `rc_schema` an; die eigentliche Logik liegt in
 * den getaggten Schema-Providern (`src/Schema/Node`) und dem GraphBuilder.
 */
final class RcStructuredData extends Plugin
{
    public const CMS_ELEMENT_FAQ = 'rc-faq';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Blog-Article-Schema nur registrieren, wenn WerklOpenBlogware installiert ist — sonst
        // verwiese der Subscriber auf ein nicht vorhandenes Event. class_exists lädt die Klasse
        // nicht, wenn sie fehlt (kein harter Fehler).
        if (class_exists(BlogPageLoadedEvent::class)) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
            $loader->load('blog.xml');
        }
    }

    public function install(InstallContext $installContext): void
    {
        $this->getCustomFieldsInstaller()->install($installContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getCustomFieldsInstaller()->addRelations($activateContext->getContext());
    }

    // Idempotenter Upsert: ergänzt neu hinzugekommene Custom-Fields (z. B. Video-Fallback) auf
    // bereits installierten Instanzen, ohne bestehende Werte zu verlieren.
    public function update(UpdateContext $updateContext): void
    {
        $installer = $this->getCustomFieldsInstaller();
        $installer->install($updateContext->getContext());
        $installer->addRelations($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if (!$uninstallContext->keepUserData()) {
            $this->getCustomFieldsInstaller()->uninstall($uninstallContext->getContext());
        }
    }

    // Während install()/activate() sind plugin-eigene Services noch nicht im DI-Container.
    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        $container = $this->container;
        if ($container === null) {
            throw new RuntimeException('Plugin container is not available.');
        }

        /** @var EntityRepository<CustomFieldSetCollection> $customFieldSetRepository */
        $customFieldSetRepository = $container->get('custom_field_set.repository');

        /** @var EntityRepository<CustomFieldSetRelationCollection> $customFieldSetRelationRepository */
        $customFieldSetRelationRepository = $container->get('custom_field_set_relation.repository');

        /** @var EntityRepository<CustomFieldCollection> $customFieldRepository */
        $customFieldRepository = $container->get('custom_field.repository');

        return new CustomFieldsInstaller(
            $customFieldSetRepository,
            $customFieldSetRelationRepository,
            $customFieldRepository,
        );
    }
}
