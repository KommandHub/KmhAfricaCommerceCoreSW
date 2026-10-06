<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionTreeBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Reads the administrative-division DAL entity and hands the flat rows to the
 * pure {@see DivisionTreeBuilder}. This is the published extension seam: swap
 * this service (it implements {@see DivisionProviderInterface}) to source the
 * tree from anywhere without touching consumers.
 *
 * Reads run in the default context; divisions are reference data, so the system
 * default language is the right fallback. A locale-specific read is a future
 * richer method on the adapter, not a change to the pure interface.
 */
final class DalDivisionProvider implements DivisionProviderInterface
{
    public function __construct(
        private readonly EntityRepository $administrativeDivisionRepository,
        private readonly DivisionTreeBuilder $treeBuilder,
    ) {
    }

    public function divisionsFor(string $countryIso2): array
    {
        return $this->treeBuilder->build($this->recordsFor($countryIso2));
    }

    public function divisionsForState(string $countryStateId): array
    {
        return $this->treeBuilder->build($this->recordsForState($countryStateId));
    }

    public function maxDepth(string $countryIso2): int
    {
        return $this->depthOf($this->divisionsFor($countryIso2));
    }

    /**
     * Divisions owned by one state (all tiers carry the state's id), mapped to
     * records. Country ISO / state code are irrelevant to the tree assembly, so
     * no extra association is loaded here.
     *
     * @return list<DivisionRecord>
     */
    private function recordsForState(string $countryStateId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryStateId', $countryStateId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $entities = $this->administrativeDivisionRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        /** @var array<string, string> $codeById */
        $codeById = [];

        foreach ($entities as $entity) {
            \assert($entity instanceof AdministrativeDivisionEntity);
            $codeById[$entity->getId()] = $entity->getCode();
        }

        $records = [];

        foreach ($entities as $entity) {
            \assert($entity instanceof AdministrativeDivisionEntity);

            $parentId = $entity->getParentId();
            $parentCode = $parentId !== null ? ($codeById[$parentId] ?? null) : null;

            $records[] = new DivisionRecord(
                '',
                '',
                $entity->getCode(),
                $entity->getName() ?? $entity->getCode(),
                $parentCode,
                $entity->getType(),
            );
        }

        return $records;
    }

    /**
     * @return list<DivisionRecord>
     */
    private function recordsFor(string $countryIso2): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryState.country.iso', strtoupper($countryIso2)));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('countryState');

        $entities = $this->administrativeDivisionRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        /** @var array<string, string> $codeById */
        $codeById = [];

        foreach ($entities as $entity) {
            \assert($entity instanceof AdministrativeDivisionEntity);
            $codeById[$entity->getId()] = $entity->getCode();
        }

        $records = [];

        foreach ($entities as $entity) {
            \assert($entity instanceof AdministrativeDivisionEntity);

            $parentId = $entity->getParentId();
            $parentCode = $parentId !== null ? ($codeById[$parentId] ?? null) : null;

            $records[] = new DivisionRecord(
                $countryIso2,
                $entity->getCountryState()?->getShortCode() ?? '',
                $entity->getCode(),
                $entity->getName() ?? $entity->getCode(),
                $parentCode,
                $entity->getType(),
            );
        }

        return $records;
    }

    /**
     * @param list<DivisionNode> $nodes
     */
    private function depthOf(array $nodes): int
    {
        $depth = 0;

        foreach ($nodes as $node) {
            $depth = max($depth, 1 + $this->depthOf($node->children));
        }

        return $depth;
    }
}
