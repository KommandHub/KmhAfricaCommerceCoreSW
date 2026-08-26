<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomerAddressAfricaDataEntity>
 */
class CustomerAddressAfricaDataCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomerAddressAfricaDataEntity::class;
    }
}
