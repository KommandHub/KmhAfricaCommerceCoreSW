<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address;

use Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer\CustomerAddressAfricaDataDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Attaches the additive African address data to core's customer_address.
 *
 * Shopware only lets an extension add associations (not plain storage columns),
 * so the extra fields live in the {@see CustomerAddressAfricaDataDefinition}
 * aggregate and hang off the address through a 1:1 association. Additive and
 * reversible — uninstall keeps the aggregate rows by default. Read it with
 * `$address->getExtension('kmhAfData')`.
 */
class CustomerAddressExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return CustomerAddressDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                'kmhAfData',
                'id',
                'customer_address_id',
                CustomerAddressAfricaDataDefinition::class,
                false,
            )
        );
    }
}
