<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy;

/**
 * A flat seed row for one administrative division below country_state.
 *
 * {@see $code} is the stable upsert key (unique per country). A division either
 * sits directly under a state ({@see $parentCode} null, attached to
 * {@see $stateCode}) or nests under another division ({@see $parentCode} set) —
 * arbitrary depth, optional per country. {@see $type} is a free label the
 * country uses for this tier ("LGA", "sub-county", "cell"); it carries no logic.
 */
final class DivisionRecord
{
    public readonly string $countryIso2;
    public readonly string $stateCode;
    public readonly string $code;
    public readonly ?string $parentCode;

    public function __construct(
        string $countryIso2,
        string $stateCode,
        string $code,
        public readonly string $name,
        ?string $parentCode = null,
        public readonly ?string $type = null,
    ) {
        $this->countryIso2 = strtoupper($countryIso2);
        $this->stateCode = strtoupper($stateCode);
        $this->code = strtoupper($code);
        $this->parentCode = $parentCode === null ? null : strtoupper($parentCode);
    }
}
