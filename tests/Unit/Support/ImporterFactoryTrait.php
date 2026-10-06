<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Support;

use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Provenance;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataSet;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * A real ReferenceImporter (it is final) over empty in-memory repositories, for
 * the tests of its callers. The dataset holds one currency, so a run reports it
 * as missing; pass no currencies for a run that is already converged, or a new
 * country for a run that creates something.
 */
trait ImporterFactoryTrait
{
    use RepositoryStubTrait;

    /**
     * @param array<string, bool> $features config key suffix => enabled
     */
    private function gate(array $features): FeatureGate
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturnCallback(
            static fn (string $key): bool => $features[substr($key, \strlen(Config::KEY))] ?? false,
        );

        return new FeatureGate(new Config($systemConfig));
    }

    private function importer(FeatureGate $gate, bool $withMissingCurrency = true, bool $withNewCountry = false): ReferenceImporter
    {
        $provider = $this->createMock(ReferenceDataProviderInterface::class);
        $provider->method('load')->willReturn(new ReferenceDataSet(
            new Provenance('test', 'ISO', '1', '2026-01-01'),
            $withMissingCurrency ? [new CurrencyRecord('XOF', 0)] : [],
            $withNewCountry ? [new CountryRecord('KE')] : [],
            [],
        ));

        return new ReferenceImporter(
            $this->repository(),
            $this->repository(),
            $this->repository(),
            $this->repository(),
            $provider,
            new Reconciler(),
            $gate,
        );
    }
}
