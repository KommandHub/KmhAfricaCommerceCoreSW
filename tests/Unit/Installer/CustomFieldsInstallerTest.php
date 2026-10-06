<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Installer;

use Kommandhub\AfricaCommerceCore\Installer\CustomFieldsInstaller;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\RepositoryStubTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;

#[CoversClass(CustomFieldsInstaller::class)]
class CustomFieldsInstallerTest extends TestCase
{
    use RepositoryStubTrait;

    private const SET_ID = '0190a0b0c0d0e0f00010203040506070';

    public function testInstallCreatesTheSetWithTheOverrideField(): void
    {
        $installer = new CustomFieldsInstaller($this->repository([], $setWrites), $this->repository());
        $installer->install(Context::createDefaultContext());

        $set = $setWrites['upsert'][0][0];
        static::assertSame('kommandhub_africacommercecore_fieldset', $set['name']);
        static::assertSame('kmh_af_merchant_override', $set['customFields'][0]['name']);
    }

    public function testInstallIsANoOpWhenTheSetExists(): void
    {
        $installer = new CustomFieldsInstaller($this->repository([$this->set()], $setWrites), $this->repository());
        $installer->install(Context::createDefaultContext());

        static::assertSame([], $setWrites['upsert']);
    }

    public function testAddRelationsAttachesOnlyTheMissingEntities(): void
    {
        $existing = new CustomFieldSetRelationEntity();
        $existing->setId(md5('relation'));
        $existing->setCustomFieldSetId(self::SET_ID);
        $existing->setEntityName('country');

        $installer = new CustomFieldsInstaller($this->repository([$this->set()]), $this->repository([$existing], $relationWrites));
        $installer->addRelations(Context::createDefaultContext());

        static::assertSame(
            ['currency', 'country_state', 'kmh_af_administrative_division'],
            array_column($relationWrites['upsert'][0], 'entityName'),
        );
    }

    public function testAddRelationsIsANoOpWhenAllExistOrNoSet(): void
    {
        $relations = [];

        foreach (['country', 'currency', 'country_state', 'kmh_af_administrative_division'] as $entity) {
            $relation = new CustomFieldSetRelationEntity();
            $relation->setId(md5($entity));
            $relation->setCustomFieldSetId(self::SET_ID);
            $relation->setEntityName($entity);
            $relations[] = $relation;
        }

        (new CustomFieldsInstaller($this->repository([$this->set()]), $this->repository($relations, $relationWrites)))
            ->addRelations(Context::createDefaultContext());
        static::assertSame([], $relationWrites['upsert']);

        (new CustomFieldsInstaller($this->repository(), $this->repository([], $relationWrites)))
            ->addRelations(Context::createDefaultContext());
        static::assertSame([], $relationWrites['upsert']);
    }

    public function testUninstallDeletesTheSetOnlyWhenPresent(): void
    {
        (new CustomFieldsInstaller($this->repository([$this->set()], $setWrites), $this->repository()))
            ->uninstall(Context::createDefaultContext());
        static::assertSame([[['id' => self::SET_ID]]], $setWrites['delete']);

        (new CustomFieldsInstaller($this->repository([], $setWrites), $this->repository()))
            ->uninstall(Context::createDefaultContext());
        static::assertSame([], $setWrites['delete']);
    }

    private function set(): CustomFieldSetEntity
    {
        $set = new CustomFieldSetEntity();
        $set->setId(self::SET_ID);
        $set->setName('kommandhub_africacommercecore_fieldset');

        return $set;
    }
}
