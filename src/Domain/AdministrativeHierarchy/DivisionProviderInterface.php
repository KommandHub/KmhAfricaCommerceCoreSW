<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy;

/**
 * Supplies the optional administrative-division hierarchy for a country.
 *
 * Published so third parties can ship a division dataset for a country without
 * patching the foundation. Optional per country: {@see maxDepth} returns 0 when
 * a country has no hierarchy below country_state.
 *
 * Default implementation: the DAL-backed DalDivisionProvider (adapter side).
 */
interface DivisionProviderInterface
{
    /**
     * Top-level divisions (each may nest via {@see DivisionNode::$children}).
     *
     * @return list<DivisionNode>
     */
    public function divisionsFor(string $countryIso2): array;

    /**
     * Divisions under one country_state (the state the shopper picked), as a
     * tree. Empty when that state has no divisions.
     *
     * @return list<DivisionNode>
     */
    public function divisionsForState(string $countryStateId): array;

    /** Depth of the hierarchy for a country; 0 means none. */
    public function maxDepth(string $countryIso2): int;
}
