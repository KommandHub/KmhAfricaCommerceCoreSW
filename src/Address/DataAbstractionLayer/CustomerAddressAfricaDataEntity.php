<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerAddressAfricaDataEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $customerAddressId;

    protected ?string $landmark = null;

    protected ?string $area = null;

    protected ?string $directions = null;

    protected ?string $digitalAddressCode = null;

    protected ?string $divisionId = null;

    protected ?CustomerAddressEntity $customerAddress = null;

    protected ?AdministrativeDivisionEntity $division = null;

    public function getCustomerAddressId(): string
    {
        return $this->customerAddressId;
    }

    public function setCustomerAddressId(string $customerAddressId): void
    {
        $this->customerAddressId = $customerAddressId;
    }

    public function getLandmark(): ?string
    {
        return $this->landmark;
    }

    public function setLandmark(?string $landmark): void
    {
        $this->landmark = $landmark;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): void
    {
        $this->area = $area;
    }

    public function getDirections(): ?string
    {
        return $this->directions;
    }

    public function setDirections(?string $directions): void
    {
        $this->directions = $directions;
    }

    public function getDigitalAddressCode(): ?string
    {
        return $this->digitalAddressCode;
    }

    public function setDigitalAddressCode(?string $digitalAddressCode): void
    {
        $this->digitalAddressCode = $digitalAddressCode;
    }

    public function getDivisionId(): ?string
    {
        return $this->divisionId;
    }

    public function setDivisionId(?string $divisionId): void
    {
        $this->divisionId = $divisionId;
    }

    public function getCustomerAddress(): ?CustomerAddressEntity
    {
        return $this->customerAddress;
    }

    public function setCustomerAddress(?CustomerAddressEntity $customerAddress): void
    {
        $this->customerAddress = $customerAddress;
    }

    public function getDivision(): ?AdministrativeDivisionEntity
    {
        return $this->division;
    }

    public function setDivision(?AdministrativeDivisionEntity $division): void
    {
        $this->division = $division;
    }
}
