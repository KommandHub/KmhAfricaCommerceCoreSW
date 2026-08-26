<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\Address;

use Kommandhub\AfricaCommerceCore\Domain\Address\AddressExtension;
use Kommandhub\AfricaCommerceCore\Domain\Address\DefaultAddressValidator;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigScope;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\PrecedenceConfigResolver;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\CountryConfiguration\Support\ArrayConfigSource;
use PHPUnit\Framework\TestCase;

final class DefaultAddressValidatorTest extends TestCase
{
    public function testWarnsWhenCountryExpectsDivisionsButNoneGiven(): void
    {
        $source = (new ArrayConfigSource())->set(ConfigScope::country('NG'), RuleKey::DivisionDepth, 2);
        $validator = new DefaultAddressValidator(new PrecedenceConfigResolver($source));

        $warnings = $validator->validate(new AddressExtension(landmark: 'Near the market'), 'NG');

        self::assertCount(1, $warnings);
    }

    public function testNoWarningWhenDivisionIsProvided(): void
    {
        $source = (new ArrayConfigSource())->set(ConfigScope::country('NG'), RuleKey::DivisionDepth, 2);
        $validator = new DefaultAddressValidator(new PrecedenceConfigResolver($source));

        $warnings = $validator->validate(new AddressExtension(divisionCode: 'NG-LA-IKEJA'), 'NG');

        self::assertSame([], $warnings);
    }

    public function testNoWarningWhenCountryHasNoHierarchy(): void
    {
        // DivisionDepth unset -> resolves to 0 -> no hint.
        $validator = new DefaultAddressValidator(new PrecedenceConfigResolver(new ArrayConfigSource()));

        self::assertSame([], $validator->validate(new AddressExtension(), 'KE'));
    }
}
