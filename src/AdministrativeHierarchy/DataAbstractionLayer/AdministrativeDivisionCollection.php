<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AdministrativeDivisionEntity>
 */
class AdministrativeDivisionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AdministrativeDivisionEntity::class;
    }
}
