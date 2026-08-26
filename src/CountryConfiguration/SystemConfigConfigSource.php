<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\CountryConfiguration;

use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigScope;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigSourceInterface;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ScopeType;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * The only place SystemConfig is read for country-configuration rules.
 *
 * Maps the library's three precedence scopes onto SystemConfig keys:
 *   - global        -> "<domain>.config.<rule>"                read at null SC
 *   - per-country   -> "<domain>.country.<ISO>.<rule>"         read at null SC
 *   - per-channel   -> "<domain>.config.<rule>"                read at the SC id
 *
 * Shopware already inherits the null-SC value when a per-SC row is absent, so a
 * per-channel read is reported as an override only when it actually differs from
 * the global value — otherwise it returns null and the resolver keeps walking.
 *
 * ponytail: a per-SC override deliberately set equal to the global value is
 * indistinguishable from inheritance here. The *effective* value is identical
 * either way; only attribution differs. Add a presence probe (getDomain) if a
 * future rule must distinguish them.
 */
final class SystemConfigConfigSource implements ConfigSourceInterface
{
    public const DOMAIN = 'KmhAfricaCommerceCoreSW';

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function get(RuleKey $key, ConfigScope $scope): mixed
    {
        return match ($scope->type) {
            ScopeType::Global => $this->systemConfig->get($this->globalKey($key)),
            ScopeType::Country => $this->systemConfig->get($this->countryKey($key, (string) $scope->id)),
            ScopeType::SalesChannel => $this->salesChannelOverride($key, (string) $scope->id),
        };
    }

    private function salesChannelOverride(RuleKey $key, string $salesChannelId): mixed
    {
        $globalKey = $this->globalKey($key);
        $scoped = $this->systemConfig->get($globalKey, $salesChannelId);
        $global = $this->systemConfig->get($globalKey);

        return $scoped === $global ? null : $scoped;
    }

    private function globalKey(RuleKey $key): string
    {
        return self::DOMAIN . '.config.' . $key->value;
    }

    private function countryKey(RuleKey $key, string $iso): string
    {
        return self::DOMAIN . '.country.' . strtoupper($iso) . '.' . $key->value;
    }
}
