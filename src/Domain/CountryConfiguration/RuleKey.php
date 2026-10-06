<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

/**
 * The shared rule contract every module reads through the resolver.
 *
 * Each case is a single country-varying capability, keyed by a stable string.
 * There is deliberately no per-country logic here: the *values* for these keys
 * live in configuration (seeded from standards, overridable per country and per
 * sales channel) and are read exclusively through {@see ConfigResolverInterface}.
 *
 * The string is the canonical key. The Shopware adapter maps it onto a
 * SystemConfig key. Add a case only together with the code that reads it
 * (address flags like postal code / state live on core's own country entity) —
 * never add an `if ($iso === ...)` anywhere instead.
 */
enum RuleKey: string
{
    /**
     * Depth of the optional administrative-division hierarchy below
     * country_state (0 = none). Read by the default address validator.
     */
    case DivisionDepth = 'divisionDepth';
}
