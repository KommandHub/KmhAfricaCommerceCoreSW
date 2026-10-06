<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Feature;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class FeatureGateTest extends TestCase
{
    public function testReadsTheFeatureFlagForTheSalesChannel(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects(static::once())
            ->method('getBool')
            ->with('KmhAfricaCommerceCoreSW.config.phoneNormalizationEnabled', 'sc-id')
            ->willReturn(true);

        static::assertTrue((new FeatureGate(new Config($systemConfig)))->isEnabled(Feature::PhoneNormalization, 'sc-id'));
    }
}
