<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;

/**
 * A versioned, provenance-tracked bundle of reference records to reconcile.
 *
 * Deliberately dumb data: the Shopware adapter walks each collection, asks the
 * {@see Reconciler} what to do per record, and applies it. Adding locale /
 * language collections later is additive — a new readonly array here.
 */
final class ReferenceDataSet
{
    /**
     * @param list<CurrencyRecord> $currencies
     * @param list<CountryRecord> $countries
     * @param list<Subdivision> $subdivisions first-tier ISO 3166-2 (country_state)
     * @param list<DivisionRecord> $divisions optional deeper administrative divisions
     */
    public function __construct(
        public readonly Provenance $provenance,
        public readonly array $currencies,
        public readonly array $countries,
        public readonly array $subdivisions,
        public readonly array $divisions = [],
    ) {
    }
}
