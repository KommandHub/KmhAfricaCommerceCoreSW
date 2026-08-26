<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Address;

use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigResolverInterface;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;

/**
 * The shipped default address validator: advisory, warn-not-block, and entirely
 * config-driven — no per-country code branch.
 *
 * Its one built-in hint is data-driven: when a country is configured with an
 * administrative-division depth but the address carries no division, it suggests
 * picking one. Everything else is left to consumers who override this seam to add
 * their own warnings. It never throws and never rejects an address.
 */
final class DefaultAddressValidator implements AddressValidatorInterface
{
    public function __construct(private readonly ConfigResolverInterface $config)
    {
    }

    public function validate(AddressExtension $address, string $countryIso2): array
    {
        $warnings = [];

        if ($this->config->int(RuleKey::DivisionDepth, $countryIso2) > 0 && $address->divisionCode === null) {
            $warnings[] = 'Select your local administrative division for a more precise address.';
        }

        return $warnings;
    }
}
