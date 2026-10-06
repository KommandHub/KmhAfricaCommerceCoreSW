<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionTreeBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Reads the administrative-division DAL entity and hands the flat rows to the
 * pure {@see DivisionTreeBuilder}. This is the published extension seam: swap
 * this service (it implements {@see DivisionProviderInterface}) to source the
 * tree from anywhere without touching consumers.
 *
 * Divisions are reference data, so they are read as the system, in the
 * requested language with the system language as fallback.
 */
final class DalDivisionProvider implements DivisionProviderInterface
{
    public function __construct(
        private readonly EntityRepository $administrativeDivisionRepository,
        private readonly DivisionTreeBuilder $treeBuilder,
    ) {
    }

    public function divisionsFor(string $countryIso2, ?string $languageId = null): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryState.country.iso', strtoupper($countryIso2)));
        $criteria->addAssociation('countryState');

        return $this->treeBuilder->build($this->records($criteria, $countryIso2, $languageId));
    }

    public function divisionsForState(string $countryStateId, ?string $languageId = null): array
    {
        // All tiers carry the state's id, so no association is needed here.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryStateId', $countryStateId));

        return $this->treeBuilder->build($this->records($criteria, '', $languageId));
    }

    public function maxDepth(string $countryIso2): int
    {
        return DivisionNode::depthOf($this->divisionsFor($countryIso2));
    }

    /**
     * Active divisions matching the criteria, mapped to records with their parent
     * resolved from id to code.
     *
     * @return list<DivisionRecord>
     */
    private function records(Criteria $criteria, string $countryIso2, ?string $languageId): array
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        // Siblings keep input order in the tree builder, so this is the dropdown order.
        $criteria->addSorting(new FieldSorting('name'));

        $entities = $this->administrativeDivisionRepository
            ->search($criteria, $this->readContext($languageId))
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

            $records[] = new DivisionRecord(
                $countryIso2,
                $entity->getCountryState()?->getShortCode() ?? '',
                $entity->getCode(),
                $entity->getName() ?? $entity->getCode(),
                $parentId !== null ? ($codeById[$parentId] ?? null) : null,
                $entity->getType(),
            );
        }

        return $records;
    }

    private function readContext(?string $languageId): Context
    {
        $languages = $languageId !== null && $languageId !== '' && $languageId !== Defaults::LANGUAGE_SYSTEM
            ? [$languageId, Defaults::LANGUAGE_SYSTEM]
            : [Defaults::LANGUAGE_SYSTEM];

        return new Context(new SystemSource(), [], Defaults::CURRENCY, $languages);
    }
}
