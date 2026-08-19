<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Install;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Legt das Custom-Field-Set `rc_schema` für Kategorie- und Landingpage-Pflege an.
 *
 * Folgt dem Suite-Muster (DAL-Installer im Plugin-Lifecycle, da plugin-eigene Services
 * während install()/activate() noch nicht im DI-Container sind). Die Felder ergänzen den
 * Graph-Builder: optionale Klartext-Beschreibung (Override) und frei konfigurierbare
 * additionalProperty-Paare.
 */
final class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'rc_schema';

    /**
     * @var list<string>
     */
    private const RELATION_ENTITIES = ['category', 'landing_page'];

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Schema.org structured data',
                'de-DE' => 'Schema.org strukturierte Daten',
                Defaults::LANGUAGE_SYSTEM => 'Schema.org structured data',
            ],
            'translated' => true,
        ],
        'allow_customer_write' => false,
        'allow_cart_expose' => false,
        'store_api_aware' => true,
        'active' => true,
        'global' => false,
        'customFields' => [
            [
                'name' => 'rc_schema_description',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => 'Schema description (override)',
                        'de-DE' => 'Schema-Beschreibung (Override)',
                        Defaults::LANGUAGE_SYSTEM => 'Schema description (override)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Optional plain-text description for the structured data. Falls back to the category description.',
                        'de-DE' => 'Optionale Klartext-Beschreibung für die strukturierten Daten. Fällt sonst auf die Kategoriebeschreibung zurück.',
                    ],
                    'customFieldPosition' => 1,
                ],
                'active' => true,
            ],
            [
                'name' => 'rc_schema_additional_properties',
                'type' => CustomFieldTypes::JSON,
                'config' => [
                    // Eigener Admin-Renderer (Name/Wert-Repeater) — Shopware rendert JSON-Felder
                    // sonst nicht als bearbeitbare Liste. sw-form-field-renderer wählt die
                    // Komponente über componentName.
                    'componentName' => 'rc-schema-additional-property',
                    'label' => [
                        'en-GB' => 'Additional properties (name/value)',
                        'de-DE' => 'Zusätzliche Eigenschaften (Name/Wert)',
                        Defaults::LANGUAGE_SYSTEM => 'Additional properties (name/value)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Free additionalProperty entries as a list of {name, value} objects.',
                        'de-DE' => 'Freie additionalProperty-Einträge als Liste von {name, value}-Objekten.',
                    ],
                    'customFieldPosition' => 2,
                ],
                'active' => true,
            ],
            [
                'name' => 'rc_schema_video_name',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => 'Video title (fallback)',
                        'de-DE' => 'Video-Titel (Fallback)',
                        Defaults::LANGUAGE_SYSTEM => 'Video title (fallback)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Page-level fallback for VideoObject.name when the youtube-video block has none. Falls back to the page title.',
                        'de-DE' => 'Seiten-Fallback für VideoObject.name, wenn am youtube-video-Block nichts gepflegt ist. Fällt sonst auf den Seitentitel zurück.',
                    ],
                    'customFieldPosition' => 3,
                ],
                'active' => true,
            ],
            [
                'name' => 'rc_schema_video_description',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => 'Video description (fallback)',
                        'de-DE' => 'Video-Beschreibung (Fallback)',
                        Defaults::LANGUAGE_SYSTEM => 'Video description (fallback)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Page-level fallback for VideoObject.description. Falls back to the page meta description.',
                        'de-DE' => 'Seiten-Fallback für VideoObject.description. Fällt sonst auf die Meta-Description der Seite zurück.',
                    ],
                    'customFieldPosition' => 4,
                ],
                'active' => true,
            ],
            [
                'name' => 'rc_schema_video_upload_date',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => 'Video upload date (fallback, ISO 8601)',
                        'de-DE' => 'Video-Upload-Datum (Fallback, ISO 8601)',
                        Defaults::LANGUAGE_SYSTEM => 'Video upload date (fallback, ISO 8601)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Page-level fallback for VideoObject.uploadDate, e.g. 2024-05-01. Required by Google.',
                        'de-DE' => 'Seiten-Fallback für VideoObject.uploadDate, z. B. 2024-05-01. Von Google gefordert.',
                    ],
                    'customFieldPosition' => 5,
                ],
                'active' => true,
            ],
            [
                'name' => 'rc_schema_video_duration',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => 'Video duration (fallback, ISO 8601)',
                        'de-DE' => 'Video-Dauer (Fallback, ISO 8601)',
                        Defaults::LANGUAGE_SYSTEM => 'Video duration (fallback, ISO 8601)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Page-level fallback for VideoObject.duration in ISO 8601, e.g. PT5M30S.',
                        'de-DE' => 'Seiten-Fallback für VideoObject.duration im ISO-8601-Format, z. B. PT5M30S.',
                    ],
                    'customFieldPosition' => 6,
                ],
                'active' => true,
            ],
        ],
    ];

    /**
     * @param EntityRepository<CustomFieldSetCollection>         $customFieldSetRepository
     * @param EntityRepository<CustomFieldSetRelationCollection> $customFieldSetRelationRepository
     * @param EntityRepository<CustomFieldCollection>            $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $payload = self::CUSTOM_FIELDSET;
        $existingSet = $this->resolveExistingSet($context);

        if ($existingSet !== null) {
            $payload['id'] = $existingSet->getId();
        }

        $payload['customFields'] = $this->enrichCustomFieldsWithExistingIds($payload['customFields'], $context);

        $this->customFieldSetRepository->upsert([$payload], $context);
    }

    public function addRelations(Context $context): void
    {
        $existingSet = $this->resolveExistingSet($context);
        if ($existingSet === null) {
            return;
        }

        $relationPayload = [];
        foreach (self::RELATION_ENTITIES as $entityName) {
            $relation = [
                'customFieldSetId' => $existingSet->getId(),
                'entityName' => $entityName,
            ];

            $existingRelationId = $this->resolveExistingRelationId($existingSet, $entityName);
            if ($existingRelationId !== null) {
                $relation['id'] = $existingRelationId;
            }

            $relationPayload[] = $relation;
        }

        $this->customFieldSetRelationRepository->upsert($relationPayload, $context);
    }

    public function uninstall(Context $context): void
    {
        $ids = $this->getCustomFieldSetIds($context);
        if ($ids === []) {
            return;
        }

        $this->customFieldSetRepository->delete(array_map(static function (string $id): array {
            return ['id' => $id];
        }, $ids), $context);
    }

    private function resolveExistingSet(Context $context): ?CustomFieldSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));
        $criteria->addAssociation('relations');

        $entity = $this->customFieldSetRepository->search($criteria, $context)->getEntities()->first();

        return $entity instanceof CustomFieldSetEntity ? $entity : null;
    }

    private function resolveExistingRelationId(CustomFieldSetEntity $set, string $entityName): ?string
    {
        $relations = $set->getRelations();
        if ($relations === null) {
            return null;
        }

        foreach ($relations as $relation) {
            if ($relation->getEntityName() === $entityName) {
                return $relation->getId();
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $customFields
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichCustomFieldsWithExistingIds(array $customFields, Context $context): array
    {
        $names = array_values(array_filter(
            array_map(static fn (array $field): mixed => $field['name'] ?? null, $customFields),
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));

        if ($names === []) {
            return $customFields;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $idByName = [];
        foreach ($this->customFieldRepository->search($criteria, $context)->getEntities() as $entity) {
            // Die Sammlung führt laut Typangabe nur Felder, und deren Name ist ein Text —
            // beide Prüfungen können nicht fehlschlagen.
            $idByName[$entity->getName()] = $entity->getId();
        }

        if ($idByName === []) {
            return $customFields;
        }

        foreach ($customFields as $index => $field) {
            $name = $field['name'] ?? null;
            if (is_string($name) && isset($idByName[$name])) {
                $customFields[$index]['id'] = $idByName[$name];
            }
        }

        return $customFields;
    }

    /**
     * @return list<string>
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }
}
