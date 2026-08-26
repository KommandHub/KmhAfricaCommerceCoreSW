<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation\AdministrativeDivisionTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

/**
 * DAL definition for the optional administrative-division tree that sits below
 * core's country_state (Nigeria LGAs, Kenya sub-counties, Rwanda cells).
 *
 * Additive and self-referencing: a division belongs to one country_state
 * (`country_state_id`) and may nest under another division (`parent_id`), giving
 * arbitrary depth as an adjacency list. The tree is assembled in PHP by the pure
 * DivisionTreeBuilder, so this entity carries no materialised-path tree behaviour.
 */
class AdministrativeDivisionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kmh_af_administrative_division';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdministrativeDivisionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdministrativeDivisionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))
                ->addFlags(new Required(), new ApiAware()),
            (new FkField('parent_id', 'parentId', self::class))->addFlags(new ApiAware()),

            // Stable upsert key, unique across the installation (prefixed per
            // country/state in the shipped data, e.g. NG-LA-IKEJA).
            (new StringField('code', 'code'))->addFlags(new Required(), new ApiAware()),
            (new IntField('level', 'level'))->addFlags(new ApiAware()),
            // Free label for this tier: "LGA", "sub-county", "cell". No logic.
            (new StringField('type', 'type'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),

            (new TranslatedField('name'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, 'id', false),
            new OneToManyAssociationField('children', self::class, 'parent_id', 'id'),
            (new TranslationsAssociationField(AdministrativeDivisionTranslationDefinition::class, 'kmh_af_administrative_division_id'))
                ->addFlags(new Required()),
        ]);
    }
}
