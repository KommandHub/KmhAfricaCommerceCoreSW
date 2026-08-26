<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * A country to activate and (optionally) configure, keyed by ISO 3166-1 alpha-2.
 *
 * The address-policy flags map one-to-one onto core's own `country` flags —
 * this is configuration, not new validation. A null flag means "leave core's
 * value alone"; a non-null flag is applied so the storefront honours it natively
 * (no custom validator, no per-country code branch). Setting
 * {@see $postalCodeRequired} false is how a no-postal-code country relaxes the
 * registration/checkout form.
 */
final class CountryRecord
{
    public readonly string $iso2;

    public function __construct(
        string $iso2,
        public readonly bool $active = true,
        public readonly ?bool $postalCodeRequired = null,
        public readonly ?bool $checkPostalCodePattern = null,
        public readonly ?bool $displayStateInRegistration = null,
        public readonly ?bool $forceStateInRegistration = null,
    ) {
        $this->iso2 = strtoupper($iso2);
    }

    /**
     * Mutable DAL fields for this record: `active` plus every non-null flag.
     * Shared by create and update so both write exactly the configured set.
     *
     * @return array<string, bool>
     */
    public function mutableFields(): array
    {
        $fields = ['active' => $this->active];

        foreach ([
            'postalCodeRequired' => $this->postalCodeRequired,
            'checkPostalCodePattern' => $this->checkPostalCodePattern,
            'displayStateInRegistration' => $this->displayStateInRegistration,
            'forceStateInRegistration' => $this->forceStateInRegistration,
        ] as $field => $value) {
            if ($value !== null) {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }
}
