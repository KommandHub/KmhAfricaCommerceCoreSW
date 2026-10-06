<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Support;

use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * An in-memory EntityRepository double for unit tests.
 *
 * `search`/`searchIds` return the given entities that match the criteria's ids
 * and its plain (non-dotted) Equals/EqualsAny filters, honouring the limit.
 * Dotted association filters are ignored — pass only the rows that should match.
 * Every `upsert`/`create`/`delete` payload is appended to `$writes[<method>]`,
 * and every `search` criteria to `$writes['search']`.
 */
trait RepositoryStubTrait
{
    /**
     * @param list<Entity> $entities
     * @param array<string, list<mixed>>|null $writes
     */
    private function repository(array $entities = [], ?array &$writes = null): EntityRepository&MockObject
    {
        $writes = ['upsert' => [], 'create' => [], 'delete' => [], 'search' => []];

        $repository = $this->createMock(EntityRepository::class);

        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($entities, &$writes): EntitySearchResult {
                $writes['search'][] = $criteria;
                $matches = $this->matching($entities, $criteria);

                return new EntitySearchResult('stub', \count($matches), new EntityCollection($matches), null, $criteria, $context);
            },
        );

        $repository->method('searchIds')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($entities): IdSearchResult {
                $data = [];

                foreach ($this->matching($entities, $criteria) as $entity) {
                    $data[$entity->getUniqueIdentifier()] = ['primaryKey' => $entity->getUniqueIdentifier(), 'data' => []];
                }

                return new IdSearchResult(\count($data), $data, $criteria, $context);
            },
        );

        foreach (['upsert', 'create', 'delete'] as $method) {
            $repository->method($method)->willReturnCallback(
                function (array $payload) use (&$writes, $method): EntityWrittenContainerEvent {
                    $writes[$method][] = $payload;

                    return $this->createStub(EntityWrittenContainerEvent::class);
                },
            );
        }

        return $repository;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return list<Entity>
     */
    private function matching(array $entities, Criteria $criteria): array
    {
        $ids = $criteria->getIds();

        $matches = array_values(array_filter($entities, static function (Entity $entity) use ($criteria, $ids): bool {
            if ($ids !== [] && !\in_array($entity->getUniqueIdentifier(), $ids, true)) {
                return false;
            }

            foreach ($criteria->getFilters() as $filter) {
                if ($filter instanceof EqualsFilter && !str_contains($filter->getField(), '.')
                    && $entity->get($filter->getField()) !== $filter->getValue()) {
                    return false;
                }

                if ($filter instanceof EqualsAnyFilter && !str_contains($filter->getField(), '.')
                    && !\in_array($entity->get($filter->getField()), $filter->getValue(), true)) {
                    return false;
                }
            }

            return true;
        }));

        return $criteria->getLimit() !== null ? \array_slice($matches, 0, $criteria->getLimit()) : $matches;
    }
}
