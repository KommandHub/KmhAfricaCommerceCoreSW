<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy;

/**
 * One node in the optional administrative-division tree that sits *below* core's
 * country_state (Nigeria LGAs, Kenya sub-counties, Rwanda cells). Self-referencing
 * so a country can nest to arbitrary depth.
 *
 * The framework-agnostic shape a provider returns; the DAL-backed entity lives
 * in the adapter and is assembled into this tree by DivisionTreeBuilder.
 */
final class DivisionNode
{
    /**
     * @param list<DivisionNode> $children
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $parentCode = null,
        public readonly array $children = [],
    ) {
    }

    /**
     * Number of tiers in a forest of nodes (0 for none).
     *
     * @param list<DivisionNode> $nodes
     */
    public static function depthOf(array $nodes): int
    {
        $depth = 0;

        foreach ($nodes as $node) {
            $depth = max($depth, 1 + self::depthOf($node->children));
        }

        return $depth;
    }
}
