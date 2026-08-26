<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

/**
 * One layer in the precedence chain: the global default, a specific country
 * (ISO 3166-1 alpha-2), or a specific sales channel.
 *
 * Immutable. Country codes are normalised to upper case so lookups are
 * case-insensitive without any per-country handling.
 */
final class ConfigScope
{
    private function __construct(
        public readonly ScopeType $type,
        public readonly ?string $id,
    ) {
    }

    public static function global(): self
    {
        return new self(ScopeType::Global, null);
    }

    public static function country(string $iso): self
    {
        return new self(ScopeType::Country, strtoupper(trim($iso)));
    }

    public static function salesChannel(string $id): self
    {
        return new self(ScopeType::SalesChannel, $id);
    }
}
