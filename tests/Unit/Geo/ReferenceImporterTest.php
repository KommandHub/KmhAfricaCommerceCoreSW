<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Geo;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Provenance;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataSet;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Subdivision;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\RepositoryStubTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(ReferenceImporter::class)]
#[UsesClass(Reconciler::class)]
#[UsesClass(ReconcileAction::class)]
#[UsesClass(ReconciliationReport::class)]
#[UsesClass(ReferenceDataSet::class)]
#[UsesClass(Provenance::class)]
#[UsesClass(CurrencyRecord::class)]
#[UsesClass(CountryRecord::class)]
#[UsesClass(Subdivision::class)]
#[UsesClass(DivisionRecord::class)]
#[UsesClass(AdministrativeDivisionEntity::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class ReferenceImporterTest extends TestCase
{
    use RepositoryStubTrait;

    private const NG = '0190a0b0c0d0e0f00010203040500001';
    private const GH = '0190a0b0c0d0e0f00010203040500002';
    private const LAGOS = '0190a0b0c0d0e0f00010203040500003';
    private const IKEJA = '0190a0b0c0d0e0f00010203040500004';

    public function testCurrenciesArePrecisionCorrectedNeverCreated(): void
    {
        $currencies = $this->repository([
            $this->currency('NGN', 2, translations: true),        // already right; unmarked translations
            $this->currency('XAF', 2),                            // CFA: must become 0
            $this->currency('TND', 2),                            // must become 3
            $this->currency('GHS', 5, entityOverride: true),      // merchant-owned
            $this->currency('KES', 5, translationOverride: true), // marker set in another language
        ], $currencyWrites);

        $report = $this->importer(
            currencies: $currencies,
            data: new ReferenceDataSet($this->provenance(), [
                new CurrencyRecord('NGN', 2),
                new CurrencyRecord('XAF', 0),
                new CurrencyRecord('TND', 3),
                new CurrencyRecord('GHS', 2),
                new CurrencyRecord('KES', 2),
                new CurrencyRecord('XOF', 0), // not installed
            ], [], []),
        )->import(Context::createDefaultContext())['currencies'];

        static::assertSame(
            ['created' => 0, 'updated' => 2, 'upToDate' => 1, 'overridden' => 2, 'missing' => 1],
            $report->toArray(),
        );

        // Precision only — never factor or name.
        static::assertSame([[
            [
                'id' => $this->id('XAF'),
                'itemRounding' => ['decimals' => 0, 'interval' => 1.0, 'roundForNet' => true],
                'totalRounding' => ['decimals' => 0, 'interval' => 1.0, 'roundForNet' => true],
            ],
            [
                'id' => $this->id('TND'),
                'itemRounding' => ['decimals' => 3, 'interval' => 0.001, 'roundForNet' => true],
                'totalRounding' => ['decimals' => 3, 'interval' => 0.001, 'roundForNet' => true],
            ],
        ]], $currencyWrites['upsert']);
    }

    public function testCountriesAreCreatedUpdatedOrLeftAlone(): void
    {
        $ghana = $this->country(self::GH, 'GH');
        $ghana->setPostalCodeRequired(true); // policy says false

        $nigeria = $this->country(self::NG, 'NG');
        $nigeria->setPostalCodeRequired(false);

        $countries = $this->repository([$ghana, $nigeria], $countryWrites);

        $report = $this->importer(
            countries: $countries,
            data: new ReferenceDataSet($this->provenance(), [], [
                new CountryRecord('GH', postalCodeRequired: false),
                new CountryRecord('NG', postalCodeRequired: false),
                new CountryRecord('KE'),
            ], []),
        )->import(Context::createDefaultContext())['countries'];

        static::assertSame(['created' => 1, 'updated' => 1, 'upToDate' => 1, 'overridden' => 0, 'missing' => 0], $report->toArray());

        $payload = $countryWrites['upsert'][0];
        static::assertSame(['id' => self::GH, 'active' => true, 'postalCodeRequired' => false], $payload[0]);
        static::assertSame('KE', $payload[1]['iso']);
        static::assertSame('KE', $payload[1]['name']);
        static::assertTrue($payload[1]['active']);
    }

    public function testSubdivisionsAttachToInstalledCountriesOnly(): void
    {
        $lagos = $this->state(self::LAGOS, self::NG, 'NG-LA', 'Lagos');
        $abuja = $this->state('0190a0b0c0d0e0f00010203040500009', self::NG, 'NG-FC', 'Abuja');

        $states = $this->repository([$lagos, $abuja], $stateWrites);

        $report = $this->importer(
            countries: $this->repository([$this->country(self::NG, 'NG')]),
            states: $states,
            data: new ReferenceDataSet($this->provenance(), [], [], [
                new Subdivision('NG', 'NG-LA', 'Lagos'),                    // up to date
                new Subdivision('NG', 'NG-FC', 'Federal Capital Territory'), // renamed
                new Subdivision('NG', 'NG-OY', 'Oyo'),                      // new
                new Subdivision('ZW', 'ZW-HA', 'Harare'),                   // country not installed
                new Subdivision('ZW', 'ZW-BU', 'Bulawayo'),                 // (cached lookup)
            ]),
        )->import(Context::createDefaultContext())['subdivisions'];

        static::assertSame(['created' => 1, 'updated' => 1, 'upToDate' => 1, 'overridden' => 0, 'missing' => 0], $report->toArray());

        $payload = $stateWrites['upsert'][0];
        static::assertSame(['id' => $abuja->getId(), 'name' => 'Federal Capital Territory'], $payload[0]);
        static::assertSame(['countryId' => self::NG, 'shortCode' => 'NG-OY', 'name' => 'Oyo'], array_slice($payload[1], 1));
    }

    public function testDivisionsAreSkippedWhenTheHierarchyIsOff(): void
    {
        $divisions = $this->repository();
        $divisions->expects(static::never())->method('search');

        $report = $this->importer(
            divisions: $divisions,
            hierarchyEnabled: false,
            data: new ReferenceDataSet($this->provenance(), [], [], [], [
                new DivisionRecord('NG', 'NG-LA', 'NG-LA-IKEJA', 'Ikeja'),
            ]),
        )->import(Context::createDefaultContext())['divisions'];

        static::assertSame(0, $report->created());
    }

    public function testNoDivisionsMeansNoQueries(): void
    {
        $divisions = $this->repository();
        $divisions->expects(static::never())->method('search');

        $report = $this->importer(divisions: $divisions)->import(Context::createDefaultContext())['divisions'];

        static::assertTrue($report->converged());
    }

    public function testDivisionsNestParentsFirstAndReconcile(): void
    {
        $ikeja = $this->division(self::IKEJA, 'NG-LA-IKEJA', 'Ikeja', null, 1, 'LGA');
        $surulere = $this->division('0190a0b0c0d0e0f00010203040500005', 'NG-LA-SURULERE', 'Old name', null, 1, 'LGA');

        $divisions = $this->repository([$ikeja, $surulere], $divisionWrites);

        $report = $this->importer(
            states: $this->repository([$this->state(self::LAGOS, self::NG, 'NG-LA', 'Lagos')]),
            divisions: $divisions,
            data: new ReferenceDataSet($this->provenance(), [], [], [], [
                // Child listed before its parent: the importer must reorder.
                new DivisionRecord('NG', 'NG-LA', 'NG-LA-IKEJA-OJODU', 'Ojodu', 'NG-LA-IKEJA', 'ward'),
                new DivisionRecord('NG', 'NG-LA', 'NG-LA-IKEJA', 'Ikeja', null, 'LGA'),
                new DivisionRecord('NG', 'NG-LA', 'NG-LA-SURULERE', 'Surulere', null, 'LGA'),
                new DivisionRecord('ZW', 'ZW-HA', 'ZW-HA-X', 'No state installed'),
            ]),
        )->import(Context::createDefaultContext())['divisions'];

        static::assertSame(['created' => 1, 'updated' => 1, 'upToDate' => 1, 'overridden' => 0, 'missing' => 0], $report->toArray());

        $payload = $divisionWrites['upsert'][0];
        static::assertSame('NG-LA-SURULERE', $payload[0]['code']);
        static::assertSame('Surulere', $payload[0]['name']);
        static::assertSame($surulere->getId(), $payload[0]['id']);

        // The new ward points at the existing Ikeja row, one level down.
        static::assertSame('NG-LA-IKEJA-OJODU', $payload[1]['code']);
        static::assertSame(self::IKEJA, $payload[1]['parentId']);
        static::assertSame(2, $payload[1]['level']);
        static::assertSame(self::LAGOS, $payload[1]['countryStateId']);
    }

    private function importer(
        ?EntityRepository $currencies = null,
        ?EntityRepository $countries = null,
        ?EntityRepository $states = null,
        ?EntityRepository $divisions = null,
        ?ReferenceDataSet $data = null,
        bool $hierarchyEnabled = true,
    ): ReferenceImporter {
        $provider = $this->createMock(ReferenceDataProviderInterface::class);
        $provider->method('load')->willReturn($data ?? new ReferenceDataSet($this->provenance(), [], [], []));

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn($hierarchyEnabled);

        return new ReferenceImporter(
            $currencies ?? $this->repository(),
            $countries ?? $this->repository(),
            $states ?? $this->repository(),
            $divisions ?? $this->repository(),
            $provider,
            new Reconciler(),
            new FeatureGate(new Config($systemConfig)),
        );
    }

    private function provenance(): Provenance
    {
        return new Provenance('test', 'ISO', '1', '2026-01-01');
    }

    private function currency(string $iso, int $decimals, bool $entityOverride = false, bool $translationOverride = false, bool $translations = false): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId($this->id($iso));
        $currency->setIsoCode($iso);
        $currency->setItemRounding(new CashRoundingConfig($decimals, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig($decimals, 0.01, true));

        if ($entityOverride) {
            $currency->setCustomFields(['kmh_af_merchant_override' => true]);
        }

        if ($translations || $translationOverride) {
            $plain = new CurrencyTranslationEntity();
            $plain->setUniqueIdentifier('plain');
            $list = [$plain];

            if ($translationOverride) {
                $marked = new CurrencyTranslationEntity();
                $marked->setUniqueIdentifier('marked');
                $marked->setCustomFields(['kmh_af_merchant_override' => true]);
                $list[] = $marked;
            }

            $currency->setTranslations(new CurrencyTranslationCollection($list));
        }

        return $currency;
    }

    private function country(string $id, string $iso): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId($id);
        $country->setIso($iso);
        $country->setActive(true);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setDisplayStateInRegistration(false);
        $country->setForceStateInRegistration(false);

        return $country;
    }

    private function state(string $id, string $countryId, string $shortCode, string $name): CountryStateEntity
    {
        $state = new CountryStateEntity();
        $state->setId($id);
        $state->setCountryId($countryId);
        $state->setShortCode($shortCode);
        $state->setName($name);

        return $state;
    }

    private function division(string $id, string $code, string $name, ?string $parentId, int $level, ?string $type): AdministrativeDivisionEntity
    {
        $division = new AdministrativeDivisionEntity();
        $division->setId($id);
        $division->setCode($code);
        $division->setName($name);
        $division->setCountryStateId(self::LAGOS);
        $division->setParentId($parentId);
        $division->setLevel($level);
        $division->setType($type);

        return $division;
    }

    private function id(string $seed): string
    {
        return md5($seed);
    }
}
