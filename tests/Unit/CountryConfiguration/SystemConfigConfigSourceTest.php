<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\CountryConfiguration;

use Kommandhub\AfricaCommerceCore\CountryConfiguration\SystemConfigConfigSource;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ConfigScope;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\RuleKey;
use Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration\ScopeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(SystemConfigConfigSource::class)]
#[UsesClass(ConfigScope::class)]
#[UsesClass(ScopeType::class)]
class SystemConfigConfigSourceTest extends TestCase
{
    private const SC = 'sc-id';

    /**
     * @param array<string, mixed> $values "<key>@<salesChannelId|global>" => value
     */
    private function source(array $values): SystemConfigConfigSource
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key, ?string $salesChannelId = null): mixed => $values[$key . '@' . ($salesChannelId ?? 'global')] ?? null,
        );

        return new SystemConfigConfigSource($systemConfig);
    }

    public function testMapsEachScopeOntoItsSystemConfigKey(): void
    {
        $source = $this->source([
            'KmhAfricaCommerceCoreSW.config.divisionDepth@global' => 1,
            'KmhAfricaCommerceCoreSW.country.NG.divisionDepth@global' => 2,
            'KmhAfricaCommerceCoreSW.config.divisionDepth@' . self::SC => 3,
        ]);

        static::assertSame(1, $source->get(RuleKey::DivisionDepth, ConfigScope::global()));
        static::assertSame(2, $source->get(RuleKey::DivisionDepth, ConfigScope::country('ng')));
        static::assertSame(3, $source->get(RuleKey::DivisionDepth, ConfigScope::salesChannel(self::SC)));
    }

    /**
     * SystemConfig returns the inherited global value for a channel with no
     * row of its own; that must not count as a channel override.
     */
    public function testInheritedChannelValueIsNotAnOverride(): void
    {
        $source = $this->source([
            'KmhAfricaCommerceCoreSW.config.divisionDepth@global' => 1,
            'KmhAfricaCommerceCoreSW.config.divisionDepth@' . self::SC => 1,
        ]);

        static::assertNull($source->get(RuleKey::DivisionDepth, ConfigScope::salesChannel(self::SC)));
    }
}
