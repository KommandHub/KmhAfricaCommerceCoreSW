<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AdministrativeDivisionTranslationEntity>
 */
class AdministrativeDivisionTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AdministrativeDivisionTranslationEntity::class;
    }
}
