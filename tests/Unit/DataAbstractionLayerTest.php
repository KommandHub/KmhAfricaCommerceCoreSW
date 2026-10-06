<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit;

use Kommandhub\AfricaCommerceCore\Address\CustomerAddressExtension;
use Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer\CustomerAddressAfricaDataCollection;
use Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer\CustomerAddressAfricaDataDefinition;
use Kommandhub\AfricaCommerceCore\Address\DataAbstractionLayer\CustomerAddressAfricaDataEntity;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionCollection;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionDefinition;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation\AdministrativeDivisionTranslationCollection;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation\AdministrativeDivisionTranslationDefinition;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\Aggregate\AdministrativeDivisionTranslation\AdministrativeDivisionTranslationEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The plugin's DAL shapes: definitions compile into the fields the migrations
 * create, entities round-trip their properties, collections hold their entity,
 * and the extension hangs the address aggregate off core's customer_address.
 */
#[CoversClass(AdministrativeDivisionDefinition::class)]
#[CoversClass(AdministrativeDivisionEntity::class)]
#[CoversClass(AdministrativeDivisionCollection::class)]
#[CoversClass(AdministrativeDivisionTranslationDefinition::class)]
#[CoversClass(AdministrativeDivisionTranslationEntity::class)]
#[CoversClass(AdministrativeDivisionTranslationCollection::class)]
#[CoversClass(CustomerAddressAfricaDataDefinition::class)]
#[CoversClass(CustomerAddressAfricaDataEntity::class)]
#[CoversClass(CustomerAddressAfricaDataCollection::class)]
#[CoversClass(CustomerAddressExtension::class)]
class DataAbstractionLayerTest extends TestCase
{
    public function testDefinitionsCompileTheirFields(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                AdministrativeDivisionDefinition::class,
                AdministrativeDivisionTranslationDefinition::class,
                CustomerAddressAfricaDataDefinition::class,
                CountryStateDefinition::class,
                CustomerAddressDefinition::class,
                LanguageDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
        );

        $this->assertDefinition(
            $registry->getByEntityName('kmh_af_administrative_division'),
            AdministrativeDivisionEntity::class,
            AdministrativeDivisionCollection::class,
            ['id', 'countryStateId', 'parentId', 'code', 'level', 'type', 'active', 'customFields', 'name', 'countryState', 'parent', 'children', 'translations'],
        );

        $translation = $registry->getByEntityName('kmh_af_administrative_division_translation');
        $this->assertDefinition(
            $translation,
            AdministrativeDivisionTranslationEntity::class,
            AdministrativeDivisionTranslationCollection::class,
            ['name'],
        );
        static::assertInstanceOf(AdministrativeDivisionTranslationDefinition::class, $translation);
        static::assertSame(AdministrativeDivisionDefinition::class, $translation->getParentDefinitionClass());

        $this->assertDefinition(
            $registry->getByEntityName('kmh_af_customer_address_data'),
            CustomerAddressAfricaDataEntity::class,
            CustomerAddressAfricaDataCollection::class,
            ['id', 'customerAddressId', 'landmark', 'area', 'directions', 'digitalAddressCode', 'divisionId', 'customFields', 'customerAddress', 'division'],
        );
    }

    public function testExtensionAddsTheOneToOneAggregate(): void
    {
        $extension = new CustomerAddressExtension();
        $fields = new FieldCollection();
        $extension->extendFields($fields);

        static::assertSame('customer_address', $extension->getEntityName());

        static::assertCount(1, $fields);
        $field = $fields->first();
        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('kmhAfData', $field->getPropertyName());
        static::assertSame(CustomerAddressAfricaDataDefinition::class, $field->getReferenceClass());
        static::assertSame('customer_address_id', $field->getReferenceField());
    }

    public function testDivisionEntityRoundTrips(): void
    {
        $state = new CountryStateEntity();
        $parent = new AdministrativeDivisionEntity();
        $children = new AdministrativeDivisionCollection();
        $translations = new AdministrativeDivisionTranslationCollection();

        $division = new AdministrativeDivisionEntity();
        $division->setCountryStateId('state');
        $division->setParentId('parent');
        $division->setCode('NG-LA-IKEJA');
        $division->setLevel(2);
        $division->setType('LGA');
        $division->setActive(false);
        $division->setName('Ikeja');
        $division->setCountryState($state);
        $division->setParent($parent);
        $division->setChildren($children);
        $division->setTranslations($translations);

        static::assertSame('state', $division->getCountryStateId());
        static::assertSame('parent', $division->getParentId());
        static::assertSame('NG-LA-IKEJA', $division->getCode());
        static::assertSame(2, $division->getLevel());
        static::assertSame('LGA', $division->getType());
        static::assertFalse($division->isActive());
        static::assertSame('Ikeja', $division->getName());
        static::assertSame($state, $division->getCountryState());
        static::assertSame($parent, $division->getParent());
        static::assertSame($children, $division->getChildren());
        static::assertSame($translations, $division->getTranslations());

        $division->setUniqueIdentifier('d');
        $children->add($division);
        static::assertCount(1, $children);
    }

    public function testDivisionTranslationEntityRoundTrips(): void
    {
        $division = new AdministrativeDivisionEntity();

        $translation = new AdministrativeDivisionTranslationEntity();
        $translation->setKmhAfAdministrativeDivisionId('division');
        $translation->setName('Ikeja');
        $translation->setKmhAfAdministrativeDivision($division);

        static::assertSame('division', $translation->getKmhAfAdministrativeDivisionId());
        static::assertSame('Ikeja', $translation->getName());
        static::assertSame($division, $translation->getKmhAfAdministrativeDivision());

        $collection = new AdministrativeDivisionTranslationCollection();
        $translation->setUniqueIdentifier('t');
        $collection->add($translation);
        static::assertCount(1, $collection);
    }

    public function testAddressDataEntityRoundTrips(): void
    {
        $address = new CustomerAddressEntity();
        $division = new AdministrativeDivisionEntity();

        $data = new CustomerAddressAfricaDataEntity();
        $data->setCustomerAddressId('address');
        $data->setLandmark('Tree');
        $data->setArea('Allen');
        $data->setDirections('Blue gate');
        $data->setDigitalAddressCode('GA-123');
        $data->setDivisionId('division');
        $data->setCustomerAddress($address);
        $data->setDivision($division);

        static::assertSame('address', $data->getCustomerAddressId());
        static::assertSame('Tree', $data->getLandmark());
        static::assertSame('Allen', $data->getArea());
        static::assertSame('Blue gate', $data->getDirections());
        static::assertSame('GA-123', $data->getDigitalAddressCode());
        static::assertSame('division', $data->getDivisionId());
        static::assertSame($address, $data->getCustomerAddress());
        static::assertSame($division, $data->getDivision());

        $collection = new CustomerAddressAfricaDataCollection();
        $data->setUniqueIdentifier('d');
        $collection->add($data);
        static::assertCount(1, $collection);
    }

    /**
     * @param list<string> $fields
     */
    private function assertDefinition(EntityDefinition $definition, string $entityClass, string $collectionClass, array $fields): void
    {
        static::assertSame($entityClass, $definition->getEntityClass());
        static::assertSame($collectionClass, $definition->getCollectionClass());

        foreach ($fields as $field) {
            static::assertTrue($definition->getFields()->has($field), "{$definition->getEntityName()} lacks {$field}");
        }
    }
}
