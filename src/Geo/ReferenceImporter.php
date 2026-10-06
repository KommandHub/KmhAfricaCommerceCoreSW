<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Geo;

use Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\DataAbstractionLayer\AdministrativeDivisionEntity;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionRecord;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Subdivision;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Util\AfricaCommerceCoreConstants;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * Reconciles the versioned reference dataset against the Shopware DAL.
 *
 * The "what to do per record" decision is the library's pure {@see Reconciler};
 * this class only supplies the three facts it needs (exists / overridden /
 * matches) by reading the DAL, then applies the chosen action. Re-running is
 * idempotent: a converged record matches and is skipped. A record a merchant
 * marked with the override custom field is never touched.
 *
 * Updates are deliberately minimal — for a currency only the rounding precision
 * is written, never the live exchange factor or name, so an import cannot clobber
 * merchant pricing data. Missing currencies are reported, never created.
 */
final class ReferenceImporter
{
    public function __construct(
        private readonly EntityRepository $currencyRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $countryStateRepository,
        private readonly EntityRepository $administrativeDivisionRepository,
        private readonly ReferenceDataProviderInterface $provider,
        private readonly Reconciler $reconciler,
        private readonly FeatureGate $featureGate,
    ) {
    }

    /**
     * @return array{currencies: ReconciliationReport, countries: ReconciliationReport, subdivisions: ReconciliationReport, divisions: ReconciliationReport}
     */
    public function import(Context $context): array
    {
        $data = $this->provider->load();

        // Order matters: subdivisions (country_state) must exist before divisions
        // attach to them on a fresh install.
        $currencies = $this->importCurrencies($data->currencies, $context);
        $countries = $this->importCountries($data->countries, $context);
        $subdivisions = $this->importSubdivisions($data->subdivisions, $context);

        // The administrative hierarchy is optional and off by default; only seed
        // its divisions when the feature is enabled.
        $divisions = $this->featureGate->isEnabled(Feature::AdministrativeHierarchy)
            ? $this->importDivisions($data->divisions, $context)
            : new ReconciliationReport();

        return [
            'currencies' => $currencies,
            'countries' => $countries,
            'subdivisions' => $subdivisions,
            'divisions' => $divisions,
        ];
    }

    /**
     * @param list<CurrencyRecord> $currencies
     */
    private function importCurrencies(array $currencies, Context $context): ReconciliationReport
    {
        $report = new ReconciliationReport();
        $payload = [];

        $existingByIso = $this->indexBy(
            $this->currencyRepository,
            'isoCode',
            array_map(static fn (CurrencyRecord $c): string => $c->isoCode, $currencies),
            $context,
        );

        foreach ($currencies as $currency) {
            $existing = $existingByIso[$currency->isoCode] ?? null;
            \assert($existing === null || $existing instanceof CurrencyEntity);

            $matches = $existing !== null
                && $existing->getItemRounding()->getDecimals() === $currency->decimals
                && $existing->getTotalRounding()->getDecimals() === $currency->decimals;

            $action = $this->reconciler->decide($existing !== null, $this->isOverridden($existing), $matches);

            // Never create a currency: its exchange factor is merchant data and a
            // placeholder would misprice the catalogue. Report it as missing.
            if ($action === ReconcileAction::Create) {
                $action = ReconcileAction::SkipMissing;
            }

            $report->record($action);

            if ($action === ReconcileAction::Update && $existing !== null) {
                // Precision only — never overwrite the merchant's live factor/name.
                $payload[] = [
                    'id' => $existing->getId(),
                    'itemRounding' => $this->rounding($currency->decimals),
                    'totalRounding' => $this->rounding($currency->decimals),
                ];
            }
        }

        if ($payload !== []) {
            $this->currencyRepository->upsert($payload, $context);
        }

        return $report;
    }

    /**
     * @param list<CountryRecord> $countries
     */
    private function importCountries(array $countries, Context $context): ReconciliationReport
    {
        $report = new ReconciliationReport();
        $payload = [];

        $existingByIso = $this->indexBy(
            $this->countryRepository,
            'iso',
            array_map(static fn (CountryRecord $c): string => $c->iso2, $countries),
            $context,
        );

        foreach ($countries as $country) {
            $existing = $existingByIso[$country->iso2] ?? null;
            \assert($existing === null || $existing instanceof CountryEntity);

            $matches = $existing !== null && $this->countryMatches($existing, $country);

            $action = $this->reconciler->decide($existing !== null, $this->isOverridden($existing), $matches);
            $report->record($action);

            if ($action === ReconcileAction::Create) {
                $payload[] = ['id' => Uuid::randomHex(), 'iso' => $country->iso2, 'name' => $country->iso2]
                    + $country->mutableFields();
            } elseif ($action === ReconcileAction::Update && $existing !== null) {
                $payload[] = ['id' => $existing->getId()] + $country->mutableFields();
            }
        }

        if ($payload !== []) {
            $this->countryRepository->upsert($payload, $context);
        }

        return $report;
    }

    /**
     * A country matches when its active state and every configured (non-null)
     * address-policy flag already equal the desired values.
     */
    private function countryMatches(CountryEntity $existing, CountryRecord $desired): bool
    {
        $current = [
            'active' => $existing->getActive(),
            'postalCodeRequired' => $existing->getPostalCodeRequired(),
            'checkPostalCodePattern' => $existing->getCheckPostalCodePattern(),
            'displayStateInRegistration' => $existing->getDisplayStateInRegistration(),
            'forceStateInRegistration' => $existing->getForceStateInRegistration(),
        ];

        foreach ($desired->mutableFields() as $field => $value) {
            if ($current[$field] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Subdivision> $subdivisions
     */
    private function importSubdivisions(array $subdivisions, Context $context): ReconciliationReport
    {
        $report = new ReconciliationReport();
        $payload = [];

        $countryByIso = $this->indexBy(
            $this->countryRepository,
            'iso',
            array_map(static fn (Subdivision $s): string => $s->countryIso2, $subdivisions),
            $context,
        );
        $existingByCode = $this->indexBy(
            $this->countryStateRepository,
            'shortCode',
            array_map(static fn (Subdivision $s): string => $s->code, $subdivisions),
            $context,
        );

        foreach ($subdivisions as $subdivision) {
            $country = $countryByIso[$subdivision->countryIso2] ?? null;

            if ($country === null) {
                // Cannot attach a state to a country that is not installed; skip.
                continue;
            }

            $countryId = $country->getUniqueIdentifier();
            $existing = $existingByCode[$subdivision->code] ?? null;
            \assert($existing === null || $existing instanceof CountryStateEntity);

            $matches = $existing !== null && $existing->getName() === $subdivision->name;

            $action = $this->reconciler->decide($existing !== null, $this->isOverridden($existing), $matches);
            $report->record($action);

            if ($action === ReconcileAction::Create) {
                $payload[] = [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryId,
                    'shortCode' => $subdivision->code,
                    'name' => $subdivision->name,
                ];
            } elseif ($action === ReconcileAction::Update && $existing !== null) {
                $payload[] = ['id' => $existing->getId(), 'name' => $subdivision->name];
            }
        }

        if ($payload !== []) {
            $this->countryStateRepository->upsert($payload, $context);
        }

        return $report;
    }

    /**
     * @param list<DivisionRecord> $divisions
     */
    private function importDivisions(array $divisions, Context $context): ReconciliationReport
    {
        $report = new ReconciliationReport();

        if ($divisions === []) {
            return $report;
        }

        /** @var array<string, DivisionRecord> $byCode */
        $byCode = [];

        foreach ($divisions as $division) {
            $byCode[$division->code] = $division;
        }

        $stateIdByCode = $this->resolveStateIds($divisions, $context);
        $existingByCode = $this->existingDivisionsByCode($divisions, $context);

        // Deterministic id per code: reuse an existing row's id, else a fresh
        // one, so a child's parentId can point at a parent created in the same
        // batch.
        $idByCode = [];

        foreach ($divisions as $division) {
            $idByCode[$division->code] = ($existingByCode[$division->code] ?? null)?->getId() ?? Uuid::randomHex();
        }

        // Parents before children so the self-referencing FK is satisfied
        // within the single upsert.
        $ordered = $divisions;
        usort(
            $ordered,
            fn (DivisionRecord $a, DivisionRecord $b): int => $this->levelOf($a, $byCode) <=> $this->levelOf($b, $byCode),
        );

        $payload = [];

        foreach ($ordered as $division) {
            $stateId = $stateIdByCode[$division->stateCode] ?? null;

            if ($stateId === null) {
                // Owning state not installed; cannot attach the division.
                continue;
            }

            $existing = $existingByCode[$division->code] ?? null;
            $parentId = $division->parentCode !== null ? ($idByCode[$division->parentCode] ?? null) : null;
            $level = $this->levelOf($division, $byCode);

            $matches = $existing !== null
                && $existing->getName() === $division->name
                && $existing->getCountryStateId() === $stateId
                && $existing->getParentId() === $parentId
                && $existing->getLevel() === $level
                && $existing->getType() === $division->type;

            $action = $this->reconciler->decide($existing !== null, $this->isOverridden($existing), $matches);
            $report->record($action);

            if ($action === ReconcileAction::Create || $action === ReconcileAction::Update) {
                $payload[] = [
                    'id' => $idByCode[$division->code],
                    'countryStateId' => $stateId,
                    'parentId' => $parentId,
                    'code' => $division->code,
                    'level' => $level,
                    'type' => $division->type,
                    'active' => true,
                    'name' => $division->name,
                ];
            }
        }

        if ($payload !== []) {
            $this->administrativeDivisionRepository->upsert($payload, $context);
        }

        return $report;
    }

    /**
     * @param list<DivisionRecord> $divisions
     *
     * @return array<string, string> state shortCode => country_state id
     */
    private function resolveStateIds(array $divisions, Context $context): array
    {
        $states = $this->indexBy(
            $this->countryStateRepository,
            'shortCode',
            array_map(static fn (DivisionRecord $d): string => $d->stateCode, $divisions),
            $context,
        );

        return array_map(static fn (Entity $state): string => $state->getUniqueIdentifier(), $states);
    }

    /**
     * @param list<DivisionRecord> $divisions
     *
     * @return array<string, AdministrativeDivisionEntity>
     */
    private function existingDivisionsByCode(array $divisions, Context $context): array
    {
        $existing = $this->indexBy(
            $this->administrativeDivisionRepository,
            'code',
            array_map(static fn (DivisionRecord $d): string => $d->code, $divisions),
            $context,
        );

        return array_map(static function (Entity $division): AdministrativeDivisionEntity {
            \assert($division instanceof AdministrativeDivisionEntity);

            return $division;
        }, $existing);
    }

    /**
     * Depth of a division within the shipped set: 1 for a top-tier unit, +1 per
     * ancestor. Guarded against a malformed cycle.
     *
     * @param array<string, DivisionRecord> $byCode
     */
    private function levelOf(DivisionRecord $division, array $byCode): int
    {
        $level = 1;
        $current = $division;
        $guard = 0;

        while ($current->parentCode !== null && isset($byCode[$current->parentCode]) && $guard < 64) {
            ++$level;
            $current = $byCode[$current->parentCode];
            ++$guard;
        }

        return $level;
    }

    /**
     * @return array{decimals: int, interval: float, roundForNet: bool}
     */
    private function rounding(int $decimals): array
    {
        return [
            'decimals' => $decimals,
            'interval' => $decimals === 0 ? 1.0 : 10 ** -$decimals,
            'roundForNet' => true,
        ];
    }

    /**
     * One query for a whole dataset, keyed by its natural key (upper-cased) —
     * never one query per record.
     *
     * Translations are loaded for the override check: on country, currency and
     * country_state the custom fields are translated, so the marker may have been
     * set while editing in any language.
     *
     * @param list<string> $values
     *
     * @return array<string, Entity>
     */
    private function indexBy(EntityRepository $repository, string $field, array $values, Context $context): array
    {
        if ($values === []) {
            return [];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter($field, array_values(array_unique($values))))
            ->addAssociation('translations');

        $entities = $repository->search($criteria, $context)->getEntities();

        $map = [];

        foreach ($entities as $entity) {
            $key = $entity->get($field);

            if (\is_string($key)) {
                $map[strtoupper($key)] = $entity;
            }
        }

        return $map;
    }

    private function isOverridden(?Entity $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        if ($this->hasOverrideMarker($entity->get('customFields'))) {
            return true;
        }

        $translations = $entity->has('translations') ? $entity->get('translations') : null;

        if (!is_iterable($translations)) {
            return false;
        }

        foreach ($translations as $translation) {
            // Not every translation carries custom fields (a division's does not).
            if ($translation instanceof Entity
                && $translation->has('customFields')
                && $this->hasOverrideMarker($translation->get('customFields'))) {
                return true;
            }
        }

        return false;
    }

    private function hasOverrideMarker(mixed $customFields): bool
    {
        return \is_array($customFields)
            && !empty($customFields[AfricaCommerceCoreConstants::CUSTOM_FIELD_MERCHANT_OVERRIDE]);
    }
}
