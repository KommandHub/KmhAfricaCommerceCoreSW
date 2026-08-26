<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Per-language name of an administrative division.
 *
 * The composite primary key (`kmh_af_administrative_division_id`, `language_id`)
 * is contributed by EntityTranslationDefinition — do not declare it here.
 */
class AdministrativeDivisionTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'kmh_af_administrative_division_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdministrativeDivisionTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdministrativeDivisionTranslationCollection::class;
    }

    public function getParentDefinitionClass(): string
    {
        return AdministrativeDivisionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
        ]);
    }
}
