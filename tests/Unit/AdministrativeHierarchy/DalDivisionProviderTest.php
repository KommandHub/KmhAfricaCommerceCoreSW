<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DalDivisionProvider;
use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionTreeBuilder;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\RepositoryStubTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;

#[CoversClass(DalDivisionProvider::class)]
#[UsesClass(DivisionTreeBuilder::class)]
#[UsesClass(DivisionRecord::class)]
#[UsesClass(DivisionNode::class)]
#[UsesClass(AdministrativeDivisionEntity::class)]
class DalDivisionProviderTest extends TestCase
{
    use RepositoryStubTrait;

    private const STATE = '0190a0b0c0d0e0f00010203040500003';
    private const IKEJA = '0190a0b0c0d0e0f00010203040500004';

    public function testBuildsTheCountryTree(): void
    {
        $provider = $this->provider($writes);

        $tree = $provider->divisionsFor('ng');

        static::assertSame(['NG-LA-IKEJA', 'NG-LA-ORPHAN'], array_map(static fn (DivisionNode $n): string => $n->code, $tree));
        static::assertSame('Ojodu', $tree[0]->children[0]->name);
        // A division without a name in the read language falls back to its code.
        static::assertSame('NG-LA-ORPHAN', $tree[1]->name);
        static::assertSame(2, $provider->maxDepth('ng'));

        $criteria = $writes['search'][0];
        static::assertInstanceOf(Criteria::class, $criteria);
        $this->assertQuery($criteria, 'countryState.country.iso', 'NG');
    }

    public function testBuildsTheStateTree(): void
    {
        $tree = $this->provider($writes)->divisionsForState(self::STATE);

        static::assertSame('NG-LA-IKEJA-OJODU', $tree[0]->children[0]->code);
        $this->assertQuery($writes['search'][0], 'countryStateId', self::STATE);
    }

    /**
     * Active rows of the requested scope, sorted by name (the dropdown order).
     */
    private function assertQuery(Criteria $criteria, string $field, string $value): void
    {
        $filters = array_map(static fn ($f): array => [$f->getField(), $f->getValue()], $criteria->getFilters());

        static::assertContains([$field, $value], $filters);
        static::assertContains(['active', true], $filters);
        static::assertEquals([new FieldSorting('name')], $criteria->getSorting());
    }

    /**
     * @param array<string, list<mixed>>|null $writes
     */
    private function provider(?array &$writes): DalDivisionProvider
    {
        $state = new CountryStateEntity();
        $state->setShortCode('NG-LA');

        $repository = $this->repository([
            $this->division(self::IKEJA, 'NG-LA-IKEJA', 'Ikeja', null, $state),
            $this->division('0190a0b0c0d0e0f00010203040500005', 'NG-LA-IKEJA-OJODU', 'Ojodu', self::IKEJA, null),
            // Parent not in the result set: promoted to a root, not dropped.
            $this->division('0190a0b0c0d0e0f00010203040500006', 'NG-LA-ORPHAN', null, 'ffffffffffffffffffffffffffffffff', null),
        ], $writes);

        return new DalDivisionProvider($repository, new DivisionTreeBuilder());
    }

    private function division(string $id, string $code, ?string $name, ?string $parentId, ?CountryStateEntity $state): AdministrativeDivisionEntity
    {
        $division = new AdministrativeDivisionEntity();
        $division->setId($id);
        $division->setCode($code);
        $division->setName($name);
        $division->setParentId($parentId);
        $division->setCountryStateId(self::STATE);
        $division->setCountryState($state);

        return $division;
    }
}
