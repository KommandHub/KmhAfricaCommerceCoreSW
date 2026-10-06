<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Domain\Geo;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionTreeBuilder;
use Kommandhub\AfricaCommerceCore\Domain\Geo\AfricanReferenceData;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use PHPUnit\Framework\TestCase;

final class AfricanReferenceDataTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function testXofAndXafAreZeroDecimals(): void
    {
        $decimals = [];

        foreach ((new AfricanReferenceData())->load()->currencies as $currency) {
            $decimals[$currency->isoCode] = $currency->decimals;
        }

        self::assertSame(0, $decimals['XOF']);
        self::assertSame(0, $decimals['XAF']);
        // A three-decimal currency is exercised too, so precision is not assumed 2.
        self::assertSame(3, $decimals['TND']);
        self::assertSame(2, $decimals['NGN']);
    }

    public function testCurrencyIsoKeysAreUnique(): void
    {
        $codes = array_map(
            static fn (CurrencyRecord $c): string => $c->isoCode,
            (new AfricanReferenceData())->load()->currencies,
        );

        self::assertSame(array_values(array_unique($codes)), $codes, 'duplicate currency ISO key');
    }

    public function testRepresentativeAddressPoliciesAreConfigured(): void
    {
        $byIso = [];

        foreach ((new AfricanReferenceData())->load()->countries as $country) {
            $byIso[$country->iso2] = $country;
        }

        // No-postal-code country: postal code relaxed via a core flag.
        self::assertInstanceOf(CountryRecord::class, $byIso['GH']);
        self::assertFalse($byIso['GH']->postalCodeRequired);
        self::assertArrayHasKey('postalCodeRequired', $byIso['GH']->mutableFields());
        self::assertFalse($byIso['GH']->mutableFields()['postalCodeRequired']);

        // State-using country: states shown in registration.
        self::assertTrue($byIso['NG']->displayStateInRegistration);

        // A plain country leaves core address flags untouched (active only).
        self::assertSame(['active' => true], $byIso['KE']->mutableFields());
    }

    public function testNigeriaHas37FirstTierSubdivisions(): void
    {
        $subdivisions = array_filter(
            (new AfricanReferenceData())->load()->subdivisions,
            static fn ($s): bool => $s->countryIso2 === 'NG',
        );

        self::assertCount(37, $subdivisions, '36 states + FCT');
    }

    public function testEveryActivatedCountryHasSubdivisions(): void
    {
        $data = (new AfricanReferenceData())->load();

        $countByIso = [];

        foreach ($data->subdivisions as $s) {
            $countByIso[$s->countryIso2] = ($countByIso[$s->countryIso2] ?? 0) + 1;
        }

        // No activated country should be left without a first-tier list.
        foreach ($data->countries as $country) {
            self::assertArrayHasKey(
                $country->iso2,
                $countByIso,
                sprintf('%s is activated but has no subdivisions', $country->iso2),
            );
            self::assertGreaterThan(0, $countByIso[$country->iso2]);
        }

        // Spot-check a few current first-tier counts to guard against a stale set.
        self::assertSame(47, $countByIso['KE'], 'Kenya has 47 counties');
        self::assertSame(9, $countByIso['ZA'], 'South Africa has 9 provinces');
        self::assertSame(16, $countByIso['GH'], 'Ghana has 16 regions');
        self::assertGreaterThan(400, \count($data->subdivisions));
    }

    public function testShippedDivisionsBuildATwoTierTreeUnderLagos(): void
    {
        $divisions = (new AfricanReferenceData())->load()->divisions;

        // All ship codes are unique and attach to the Lagos state.
        $codes = [];

        foreach ($divisions as $d) {
            self::assertSame('NG-LA', $d->stateCode);
            $codes[] = $d->code;
        }
        self::assertSame(array_values(array_unique($codes)), $codes, 'duplicate division code');

        $tree = (new DivisionTreeBuilder())->build($divisions);

        $ikeja = null;

        foreach ($tree as $node) {
            if ($node->code === 'NG-LA-IKEJA') {
                $ikeja = $node;
            }
        }

        self::assertNotNull($ikeja, 'Ikeja LGA is a top-tier root');
        self::assertCount(2, $ikeja->children, 'Ikeja has two wards below it');
    }

    public function testSubdivisionCodesAreIsoPrefixedAndUnique(): void
    {
        $codes = [];

        foreach ((new AfricanReferenceData())->load()->subdivisions as $s) {
            self::assertStringStartsWith($s->countryIso2 . '-', $s->code);
            $codes[] = $s->code;
        }

        self::assertSame(array_values(array_unique($codes)), $codes, 'duplicate subdivision code');
    }

    public function testMissingDataFileFailsLoudly(): void
    {
        $this->expectExceptionMessage('Cannot read reference data file');

        (new AfricanReferenceData($this->dataDir([])))->load();
    }

    public function testNonArrayDataFileFailsLoudly(): void
    {
        $this->expectExceptionMessage('is not a JSON array');

        (new AfricanReferenceData($this->dataDir(['subdivisions.json' => '"nope"'])))->load();
    }

    public function testNonStringFieldFailsLoudly(): void
    {
        $this->expectExceptionMessage('field "code" must be a string');

        (new AfricanReferenceData($this->dataDir([
            'subdivisions.json' => '[{"country": "NG", "code": 12, "name": "Lagos"}]',
        ])))->load();
    }

    /**
     * @param array<string, string> $files
     */
    private function dataDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/kmh-af-data-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $this->tempDirs[] = $dir;

        foreach ($files as $name => $json) {
            file_put_contents($dir . '/' . $name, $json);
        }

        return $dir;
    }
}
