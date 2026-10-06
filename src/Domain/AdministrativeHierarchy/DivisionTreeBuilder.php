<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy;

/**
 * Pure flat-to-nested transform: a list of {@see DivisionRecord} becomes a list
 * of root {@see DivisionNode}s with children linked by parent code.
 *
 * Arbitrary depth, no framework. A record whose parent code is missing from the
 * set is promoted to a root rather than dropped (so a partial seed still renders).
 * A cycle is broken by a visited guard, so malformed data cannot recurse forever.
 * Input order is preserved among siblings.
 */
final class DivisionTreeBuilder
{
    /**
     * @param list<DivisionRecord> $records
     *
     * @return list<DivisionNode>
     */
    public function build(array $records): array
    {
        /** @var array<string, list<DivisionRecord>> $childrenByParent */
        $childrenByParent = [];
        /** @var array<string, true> $known */
        $known = [];

        foreach ($records as $record) {
            $known[$record->code] = true;
        }

        foreach ($records as $record) {
            $parent = $record->parentCode !== null && isset($known[$record->parentCode])
                ? $record->parentCode
                : '';
            $childrenByParent[$parent][] = $record;
        }

        $roots = [];

        foreach ($childrenByParent[''] ?? [] as $record) {
            $roots[] = $this->node($record, $childrenByParent, []);
        }

        return $roots;
    }

    /**
     * @param array<string, list<DivisionRecord>> $childrenByParent
     * @param array<string, true> $visited
     */
    private function node(DivisionRecord $record, array $childrenByParent, array $visited): DivisionNode
    {
        $visited[$record->code] = true;

        $children = [];

        foreach ($childrenByParent[$record->code] ?? [] as $child) {
            if (isset($visited[$child->code])) {
                // Cycle: a descendant points back at an ancestor. Stop here.
                continue;
            }
            $children[] = $this->node($child, $childrenByParent, $visited);
        }

        return new DivisionNode($record->code, $record->name, $record->parentCode, $children);
    }
}
