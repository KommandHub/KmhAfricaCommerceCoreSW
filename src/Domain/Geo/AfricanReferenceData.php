<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;

/**
 * The shipped, versioned African reference dataset.
 *
 * Anchored to ISO 4217 (currency minor units), ISO 3166-1 alpha-2 (countries),
 * and ISO 3166-2 (subdivisions). Currencies and country policies are curated
 * inline; the large subdivision and division sets live as versioned JSON data
 * files under `data/` (not an SQL/PHP blob), loaded here. The reconciler keys off
 * ISO codes so re-running always converges.
 *
 * Subdivision data is first-tier ISO 3166-2 for every activated country, sourced
 * from amckenna41/iso3166-2 (ISO OBP-derived). Deeper administrative units belong
 * to the division hierarchy below country_state, not here.
 */
final class AfricanReferenceData implements ReferenceDataProviderInterface
{
    public const VERSION = '2026.1';

    /**
     * @param string $dataDir where the versioned JSON files live (overridable for tests)
     */
    public function __construct(private readonly string $dataDir = __DIR__ . '/data')
    {
    }

    public function load(): ReferenceDataSet
    {
        return new ReferenceDataSet(
            new Provenance(
                source: 'ISO OBP via amckenna41/iso3166-2; ISO 4217',
                standard: 'ISO 4217, ISO 3166-1, ISO 3166-2',
                version: self::VERSION,
                retrievedAt: '2026-08-25',
            ),
            $this->currencies(),
            $this->countries(),
            $this->subdivisions(),
            $this->divisions(),
        );
    }

    /**
     * First-tier ISO 3166-2 subdivisions for every activated country, loaded from
     * the versioned data file.
     *
     * @return list<Subdivision>
     */
    private function subdivisions(): array
    {
        $out = [];

        foreach ($this->readJson('subdivisions.json') as $row) {
            $out[] = new Subdivision(
                $this->string($row, 'country'),
                $this->string($row, 'code'),
                $this->string($row, 'name'),
            );
        }

        return $out;
    }

    /**
     * Optional administrative divisions below country_state, loaded from the
     * versioned data file. Ships a Lagos sample (LGAs, with wards under Ikeja) to
     * prove the tree nests to arbitrary depth; extend by adding rows.
     *
     * @return list<DivisionRecord>
     */
    private function divisions(): array
    {
        $out = [];

        foreach ($this->readJson('divisions.json') as $row) {
            $out[] = new DivisionRecord(
                $this->string($row, 'country'),
                $this->string($row, 'state'),
                $this->string($row, 'code'),
                $this->string($row, 'name'),
                isset($row['parent']) ? $this->string($row, 'parent') : null,
                isset($row['type']) ? $this->string($row, 'type') : null,
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!\is_string($value)) {
            throw new \RuntimeException(sprintf('Reference data row field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJson(string $file): array
    {
        $path = $this->dataDir . '/' . $file;
        $json = @file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException(sprintf('Cannot read reference data file: %s', $path));
        }

        /** @var mixed $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new \RuntimeException(sprintf('Reference data file is not a JSON array: %s', $file));
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /** @return list<CurrencyRecord> */
    private function currencies(): array
    {
        // [iso => decimals] per ISO 4217. Zero-decimal (XOF/XAF/...) and
        // three-decimal (TND) are in scope.
        $rows = [
            'XOF' => 0, 'XAF' => 0, 'RWF' => 0, 'UGX' => 0, 'GNF' => 0,
            'BIF' => 0, 'DJF' => 0, 'KMF' => 0,
            'TND' => 3,
            'NGN' => 2, 'GHS' => 2, 'KES' => 2, 'ZAR' => 2, 'EGP' => 2,
            'MAD' => 2, 'DZD' => 2, 'TZS' => 2, 'ETB' => 2,
        ];

        $out = [];

        foreach ($rows as $iso => $decimals) {
            $out[] = new CurrencyRecord($iso, $decimals);
        }

        return $out;
    }

    /** @return list<CountryRecord> */
    private function countries(): array
    {
        // Representative address policies (the rest are activated with core
        // defaults untouched):
        //   GH — no postal code system: relax the postal-code field entirely.
        //   NG — uses states: show them in registration; postal code optional.
        $records = [
            new CountryRecord('GH', postalCodeRequired: false, checkPostalCodePattern: false),
            new CountryRecord('NG', postalCodeRequired: false, displayStateInRegistration: true),
        ];

        $plain = [
            'KE', 'ZA', 'RW', 'EG', 'CI', 'SN', 'TZ', 'UG', 'ET', 'MA',
            'DZ', 'TN', 'CM', 'CD', 'CG', 'BJ', 'BF', 'ML', 'NE', 'TG',
            'GN', 'DJ', 'KM', 'BI',
        ];

        foreach ($plain as $code) {
            $records[] = new CountryRecord($code);
        }

        return $records;
    }
}
