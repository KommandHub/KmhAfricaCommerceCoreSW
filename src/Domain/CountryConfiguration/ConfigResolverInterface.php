<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

/**
 * The one way to read effective configuration.
 *
 * Precedence is fixed: global default -> per-country -> per-sales-channel, the
 * most specific configured layer winning. Nothing in the system reads effective
 * configuration any other way.
 */
interface ConfigResolverInterface
{
    /**
     * Effective raw value for $key, or null if unset at every applicable scope.
     */
    public function resolve(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
    ): mixed;

    /**
     * Effective value coerced to bool, using $default when unset everywhere.
     */
    public function bool(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
        bool $default = false,
    ): bool;

    /**
     * Effective value coerced to int, using $default when unset everywhere.
     */
    public function int(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
        int $default = 0,
    ): int;
}
