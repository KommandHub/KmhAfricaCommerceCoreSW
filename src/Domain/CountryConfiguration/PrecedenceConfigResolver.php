<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

/**
 * Pure precedence resolver: global default -> per-country -> per-sales-channel.
 *
 * Walks the applicable scopes from least to most specific; the last layer that
 * returns a non-null value wins. A scope contributes nothing when its source
 * returns null, so a missing per-country or per-sales-channel value transparently
 * falls back to the layer beneath it. No I/O and no per-country logic — all data
 * comes from the injected {@see ConfigSourceInterface}.
 */
final class PrecedenceConfigResolver implements ConfigResolverInterface
{
    public function __construct(private readonly ConfigSourceInterface $source)
    {
    }

    public function resolve(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
    ): mixed {
        $value = null;

        foreach ($this->scopeChain($countryIso, $salesChannelId) as $scope) {
            $layerValue = $this->source->get($key, $scope);
            if ($layerValue !== null) {
                $value = $layerValue;
            }
        }

        return $value;
    }

    public function bool(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
        bool $default = false,
    ): bool {
        $value = $this->resolve($key, $countryIso, $salesChannelId);

        return $value === null ? $default : (bool) $value;
    }

    public function int(
        RuleKey $key,
        ?string $countryIso = null,
        ?string $salesChannelId = null,
        int $default = 0,
    ): int {
        $value = $this->resolve($key, $countryIso, $salesChannelId);

        return $value === null ? $default : (int) $value;
    }

    /**
     * @return list<ConfigScope> least-specific first
     */
    private function scopeChain(?string $countryIso, ?string $salesChannelId): array
    {
        $chain = [ConfigScope::global()];

        if ($countryIso !== null && $countryIso !== '') {
            $chain[] = ConfigScope::country($countryIso);
        }

        if ($salesChannelId !== null && $salesChannelId !== '') {
            $chain[] = ConfigScope::salesChannel($salesChannelId);
        }

        return $chain;
    }
}
