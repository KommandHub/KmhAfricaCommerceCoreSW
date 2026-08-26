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
 * SystemConfig key; a future store-API or CLI reads the same enum. Add cases as
 * new capabilities appear — never add an `if ($iso === ...)` anywhere instead.
 */
enum RuleKey: string
{
    /** Whether a postal code is required for an address in this scope. */
    case PostalCodeRequired = 'postalCodeRequired';

    /** Whether the administrative state/region is required in registration. */
    case StateRequired = 'stateRequired';

    /** Whether the state field is shown in registration at all. */
    case StateDisplayed = 'stateDisplayed';

    /**
     * Depth of the optional administrative-division hierarchy below
     * country_state (0 = none). Consumed by the Administrative-Hierarchy module.
     */
    case DivisionDepth = 'divisionDepth';

    /**
     * Phone-number handling: whether an unparseable number blocks submission
     * (true) or only warns (false, the standards-anchored default).
     */
    case PhoneStrict = 'phoneStrict';
}
