<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation\AdministrativeDivisionTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;

class AdministrativeDivisionEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $countryStateId;

    protected ?string $parentId = null;

    protected string $code;

    protected int $level = 1;

    protected ?string $type = null;

    protected bool $active = true;

    /** Translated: null until loaded for the requested language. */
    protected ?string $name = null;

    protected ?CountryStateEntity $countryState = null;

    protected ?AdministrativeDivisionEntity $parent = null;

    protected ?AdministrativeDivisionCollection $children = null;

    protected ?AdministrativeDivisionTranslationCollection $translations = null;

    public function getCountryStateId(): string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountryState(): ?CountryStateEntity
    {
        return $this->countryState;
    }

    public function setCountryState(?CountryStateEntity $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?AdministrativeDivisionCollection
    {
        return $this->children;
    }

    public function setChildren(AdministrativeDivisionCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?AdministrativeDivisionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AdministrativeDivisionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
