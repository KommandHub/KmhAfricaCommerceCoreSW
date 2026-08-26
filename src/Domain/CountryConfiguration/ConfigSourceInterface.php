<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

/**
 * Supplies raw configuration values for one scope layer.
 *
 * The library ships the pure resolver; adapters implement this to read from
 * their backing store (the Shopware adapter reads SystemConfig). Return `null`
 * when the key is unset *at that scope* so the resolver can fall back to a less
 * specific layer — `null` means "not configured here", never "configured false".
 */
interface ConfigSourceInterface
{
    public function get(RuleKey $key, ConfigScope $scope): mixed;
}
