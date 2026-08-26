<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class AdministrativeDivisionTranslationEntity extends TranslationEntity
{
    /**
     * Named after the parent entity, which the DAL derives from
     * `kmh_af_administrative_division` — the prefix is part of the property
     * name, not decoration that can be trimmed.
     */
    protected string $kmhAfAdministrativeDivisionId;

    protected string $name;

    protected ?AdministrativeDivisionEntity $kmhAfAdministrativeDivision = null;

    public function getKmhAfAdministrativeDivisionId(): string
    {
        return $this->kmhAfAdministrativeDivisionId;
    }

    public function setKmhAfAdministrativeDivisionId(string $id): void
    {
        $this->kmhAfAdministrativeDivisionId = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKmhAfAdministrativeDivision(): ?AdministrativeDivisionEntity
    {
        return $this->kmhAfAdministrativeDivision;
    }

    public function setKmhAfAdministrativeDivision(?AdministrativeDivisionEntity $division): void
    {
        $this->kmhAfAdministrativeDivision = $division;
    }
}
