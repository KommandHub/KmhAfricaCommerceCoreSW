<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\CountryConfiguration\Support;

use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigScope;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigSourceInterface;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;

/**
 * In-memory {@see ConfigSourceInterface} for resolver tests: set values per
 * scope, unset scopes return null so fallback can be exercised.
 *
 * @internal test double
 */
final class ArrayConfigSource implements ConfigSourceInterface
{
    /** @var array<string, array<string, mixed>> [scopeId][ruleKey] => value */
    private array $data = [];

    public function set(ConfigScope $scope, RuleKey $key, mixed $value): self
    {
        $this->data[$this->scopeId($scope)][$key->value] = $value;

        return $this;
    }

    public function get(RuleKey $key, ConfigScope $scope): mixed
    {
        return $this->data[$this->scopeId($scope)][$key->value] ?? null;
    }

    private function scopeId(ConfigScope $scope): string
    {
        return $scope->type->value . ':' . ($scope->id ?? '');
    }
}
