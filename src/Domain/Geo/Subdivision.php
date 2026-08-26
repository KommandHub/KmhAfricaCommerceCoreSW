<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * A first-tier ISO 3166-2 subdivision (state/region) to seed into country_state.
 *
 * {@see $code} is the full ISO 3166-2 code (e.g. "NG-LA") and the stable upsert
 * key. Deeper administrative divisions (LGAs, sub-counties, cells) are NOT this
 * type — they belong to the Administrative-Hierarchy module's own tree.
 */
final class Subdivision
{
    public readonly string $countryIso2;
    public readonly string $code;

    public function __construct(string $countryIso2, string $code, public readonly string $name)
    {
        $this->countryIso2 = strtoupper($countryIso2);
        $this->code = strtoupper($code);
    }
}
