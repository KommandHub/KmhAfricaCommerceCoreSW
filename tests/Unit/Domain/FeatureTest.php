<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Feature::class)]
class FeatureTest extends TestCase
{
    /**
     * The keys are the config.xml field names — renaming one silently turns the
     * feature off for every merchant.
     */
    public function testConfigKeysMatchTheConfigFields(): void
    {
        static::assertSame(
            ['referenceDataEnabled', 'administrativeHierarchyEnabled', 'addressExtensionsEnabled', 'phoneNormalizationEnabled'],
            array_map(static fn (Feature $f): string => $f->configKey(), Feature::cases()),
        );

        $configXml = (string)file_get_contents(__DIR__ . '/../../../src/Resources/config/config.xml');

        foreach (Feature::cases() as $feature) {
            static::assertStringContainsString('<name>' . $feature->configKey() . '</name>', $configXml);
        }
    }
}
