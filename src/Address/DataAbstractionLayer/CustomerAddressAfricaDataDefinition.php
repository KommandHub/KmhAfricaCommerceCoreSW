<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * The additive African address data, held in its own 1:1 aggregate table rather
 * than as columns on customer_address.
 *
 * Shopware forbids an EntityExtension from adding plain storage columns to a core
 * entity (only associations / FK-with-association / runtime fields). So the extra
 * fields live here and attach to core's customer_address through a
 * OneToOneAssociationField — additive, reversible, and rule-compliant.
 */
class CustomerAddressAfricaDataDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kmh_af_customer_address_data';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CustomerAddressAfricaDataEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CustomerAddressAfricaDataCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_address_id', 'customerAddressId', CustomerAddressDefinition::class))
                ->addFlags(new Required(), new ApiAware()),

            (new StringField('landmark', 'landmark'))->addFlags(new ApiAware()),
            (new StringField('area', 'area'))->addFlags(new ApiAware()),
            (new LongTextField('directions', 'directions'))->addFlags(new ApiAware()),
            (new StringField('digital_address_code', 'digitalAddressCode'))->addFlags(new ApiAware()),

            (new FkField('division_id', 'divisionId', AdministrativeDivisionDefinition::class))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),

            new ManyToOneAssociationField('customerAddress', 'customer_address_id', CustomerAddressDefinition::class, 'id', false),
            new ManyToOneAssociationField('division', 'division_id', AdministrativeDivisionDefinition::class, 'id', false),
        ]);
    }
}
