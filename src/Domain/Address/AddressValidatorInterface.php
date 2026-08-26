<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Address;

/**
 * The address-validation seam (documented decoration seam #1).
 *
 * Warn, don't block, by default: returns human-readable warnings rather than
 * throwing, so an unusual-but-valid African address still checks out. Country
 * rules come from configuration, never a per-country code branch.
 *
 * Default implementation: {@see DefaultAddressValidator}. Override the service
 * alias to add or relax warnings without decorating core.
 */
interface AddressValidatorInterface
{
    /**
     * @return list<string>
     */
    public function validate(AddressExtension $address, string $countryIso2): array;
}
