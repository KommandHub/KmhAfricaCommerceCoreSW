<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Address;

/**
 * The additive, standards-anchored address fields African addresses often need
 * and core lacks. Every field is optional — this augments the core address, it
 * never replaces core fields.
 *
 * {@see $digitalAddressCode} is a neutral code slot (e.g. a GhanaPostGPS code);
 * the foundation stores it and never integrates a live lookup service.
 *
 * Surfaced on core's customer_address via the adapter's CustomerAddressExtension
 * (nullable columns). Storefront placement through core's
 * country_translation.addressFormat mechanism is future work gated by the
 * address-extensions feature.
 */
final class AddressExtension
{
    public function __construct(
        public readonly ?string $landmark = null,
        public readonly ?string $area = null,
        public readonly ?string $directions = null,
        public readonly ?string $digitalAddressCode = null,
        public readonly ?string $divisionCode = null,
    ) {
    }
}
