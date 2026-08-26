<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Installer;

use Kommandhub\AfricaCommerceCore\Util\AfricaCommerceCoreConstants;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionDefinition;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Defaults;

/**
 * Creates this plugin's custom field set and its entity relations.
 *
 * Every method is idempotent: the plugin bootstrap runs them on both install
 * and update, so a second run must be a no-op rather than a duplicate.
 *
 * Field names are global across the installation — always prefix them with the
 * plugin slug. Keep the canonical names in one constants class so a rename
 * cannot silently orphan stored data.
 */
class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'kommandhub_africacommercecore_fieldset';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'African Commerce Foundation for Shopware 6',
                'de-DE' => 'African Commerce Foundation for Shopware 6',
                'fr-FR' => 'African Commerce Foundation for Shopware 6',
                Defaults::LANGUAGE_SYSTEM => 'African Commerce Foundation for Shopware 6',
            ],
        ],
        'customFields' => [
            [
                'name' => AfricaCommerceCoreConstants::CUSTOM_FIELD_MERCHANT_OVERRIDE,
                'type' => CustomFieldTypes::BOOL,
                'config' => [
                    'label' => [
                        'en-GB' => 'Merchant override (do not auto-import)',
                        'de-DE' => 'Manuell überschrieben (kein Auto-Import)',
                        'fr-FR' => 'Remplacé manuellement (pas d\'import auto)',
                        Defaults::LANGUAGE_SYSTEM => 'Merchant override (do not auto-import)',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
        ],
    ];

    /**
     * Entities the override marker is attached to — the reference data the
     * importer reconciles. Setting the marker on any of these protects that
     * record from being overwritten on the next import.
     */
    private const RELATED_ENTITIES = [
        CountryDefinition::ENTITY_NAME,
        CurrencyDefinition::ENTITY_NAME,
        CountryStateDefinition::ENTITY_NAME,
        AdministrativeDivisionDefinition::ENTITY_NAME,
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        if ($this->getCustomFieldSetIds($context) !== []) {
            return;
        }

        $this->customFieldSetRepository->upsert([self::CUSTOM_FIELDSET], $context);
    }

    public function addRelations(Context $context): void
    {
        $relationsToInsert = [];

        foreach ($this->getCustomFieldSetIds($context) as $customFieldSetId) {
            foreach (self::RELATED_ENTITIES as $entityName) {
                if ($this->relationExists($context, $customFieldSetId, $entityName)) {
                    continue;
                }

                $relationsToInsert[] = [
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $entityName,
                ];
            }
        }

        if ($relationsToInsert === []) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert($relationsToInsert, $context);
    }

    /**
     * Only called when the merchant did NOT ask to keep user data — deleting the
     * field set deletes every value stored in it.
     */
    public function uninstall(Context $context): void
    {
        $ids = $this->getCustomFieldSetIds($context);

        if ($ids === []) {
            return;
        }

        $this->customFieldSetRepository->delete(
            array_map(static fn (string $id) => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function relationExists(Context $context, string $customFieldSetId, string $entityName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
