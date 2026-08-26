<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\CountryConfiguration;

use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigScope;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\PrecedenceConfigResolver;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\CountryConfiguration\Support\ArrayConfigSource;
use PHPUnit\Framework\TestCase;

final class PrecedenceConfigResolverTest extends TestCase
{
    private const NG = 'NG';
    private const SC = 'sales-channel-id';

    public function testReturnsNullWhenUnsetEverywhere(): void
    {
        $resolver = new PrecedenceConfigResolver(new ArrayConfigSource());

        self::assertNull($resolver->resolve(RuleKey::PostalCodeRequired, self::NG, self::SC));
    }

    public function testGlobalDefaultAppliesWhenNoMoreSpecificLayer(): void
    {
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::global(), RuleKey::PostalCodeRequired, true);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertTrue($resolver->resolve(RuleKey::PostalCodeRequired, self::NG, self::SC));
    }

    public function testCountryOverridesGlobal(): void
    {
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::global(), RuleKey::PostalCodeRequired, true)
            ->set(ConfigScope::country(self::NG), RuleKey::PostalCodeRequired, false);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertFalse($resolver->resolve(RuleKey::PostalCodeRequired, self::NG));
        // A different country is not affected by NG's override.
        self::assertTrue($resolver->resolve(RuleKey::PostalCodeRequired, 'KE'));
    }

    public function testSalesChannelOverridesCountryAndGlobal(): void
    {
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::global(), RuleKey::PostalCodeRequired, true)
            ->set(ConfigScope::country(self::NG), RuleKey::PostalCodeRequired, true)
            ->set(ConfigScope::salesChannel(self::SC), RuleKey::PostalCodeRequired, false);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertFalse($resolver->resolve(RuleKey::PostalCodeRequired, self::NG, self::SC));
    }

    public function testMissingMiddleLayerFallsThroughToGlobal(): void
    {
        // Country unset, sales-channel unset -> global wins.
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::global(), RuleKey::DivisionDepth, 2);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertSame(2, $resolver->int(RuleKey::DivisionDepth, self::NG, self::SC));
    }

    public function testSalesChannelWinsEvenWhenCountryUnset(): void
    {
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::global(), RuleKey::PostalCodeRequired, true)
            ->set(ConfigScope::salesChannel(self::SC), RuleKey::PostalCodeRequired, false);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertFalse($resolver->resolve(RuleKey::PostalCodeRequired, self::NG, self::SC));
    }

    public function testCountryLookupIsCaseInsensitive(): void
    {
        $source = (new ArrayConfigSource())
            ->set(ConfigScope::country('NG'), RuleKey::PostalCodeRequired, false);

        $resolver = new PrecedenceConfigResolver($source);

        self::assertFalse($resolver->resolve(RuleKey::PostalCodeRequired, 'ng'));
    }

    public function testBoolAppliesDefaultWhenUnset(): void
    {
        $resolver = new PrecedenceConfigResolver(new ArrayConfigSource());

        self::assertTrue($resolver->bool(RuleKey::PhoneStrict, default: true));
        self::assertFalse($resolver->bool(RuleKey::PhoneStrict, default: false));
    }
}
