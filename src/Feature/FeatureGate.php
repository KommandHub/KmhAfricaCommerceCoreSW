<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Feature;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;

/**
 * The single place every subsystem asks "am I turned on?".
 *
 * Sales-channel aware: a feature can be enabled globally and disabled for one
 * channel (or the reverse) through Shopware's own SystemConfig inheritance.
 * Defaults come from config.xml, so an unset flag reads as its declared default.
 */
final class FeatureGate
{
    public function __construct(private readonly Config $config)
    {
    }

    public function isEnabled(Feature $feature, ?string $salesChannelId = null): bool
    {
        return $this->config->getBool($feature->configKey(), $salesChannelId);
    }
}
