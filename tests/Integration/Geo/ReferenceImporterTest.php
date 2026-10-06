<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Integration\Geo;

use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Tests\Integration\PluginKernelTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * In-stack proof of the reference importer. Requires a booted, plugin-aware
 * Shopware kernel and database — runs under `make test`, not in the
 * framework-agnostic Domain suite. Wrapped in a rolled-back transaction, so it
 * leaves no state behind.
 */
#[Group('kernel')]
#[CoversNothing]
class ReferenceImporterTest extends TestCase
{
    use PluginKernelTrait;

    public function testSecondRunConvergesAndCorrectsCfaDecimals(): void
    {
        $context = Context::createDefaultContext();
        $currencies = $this->currencyRepository();

        // Merchant-created CFA currencies with the wrong (2-decimal) precision and
        // a real exchange factor the import must not touch.
        foreach (['XOF', 'XAF'] as $iso) {
            if ($this->currency($iso, $context) === null) {
                $currencies->create([[
                    'id' => Uuid::randomHex(),
                    'isoCode' => $iso,
                    'name' => $iso,
                    'shortName' => $iso,
                    'symbol' => $iso,
                    'factor' => 655.957,
                    'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
                    'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
                ]], $context);
            }
        }

        $importer = $this->service(ReferenceImporter::class);
        static::assertInstanceOf(ReferenceImporter::class, $importer);

        // First run may create/update; the second run must change nothing.
        $importer->import($context);
        $second = $importer->import($context);

        foreach ($second as $section => $report) {
            static::assertSame(0, $report->created(), "second run created {$section}");
            static::assertSame(0, $report->updated(), "second run updated {$section}");
            static::assertTrue($report->converged(), "{$section} did not converge");
        }

        // The flagship correction: CFA francs carry zero minor units (ISO 4217),
        // and the merchant's factor survives.
        foreach (['XOF', 'XAF'] as $iso) {
            $currency = $this->currency($iso, $context);

            static::assertInstanceOf(CurrencyEntity::class, $currency, "{$iso} missing after import");
            static::assertSame(0, $currency->getItemRounding()->getDecimals(), "{$iso} itemRounding");
            static::assertSame(0, $currency->getTotalRounding()->getDecimals(), "{$iso} totalRounding");
            static::assertNotSame(1.0, $currency->getFactor(), "{$iso} factor was overwritten");
        }
    }

    public function testMissingCurrenciesAreReportedNeverCreated(): void
    {
        $context = Context::createDefaultContext();

        $importer = $this->service(ReferenceImporter::class);
        static::assertInstanceOf(ReferenceImporter::class, $importer);

        $hadKmf = $this->currency('KMF', $context) !== null;

        $report = $importer->import($context)['currencies'];

        // A currency needs a real exchange factor; the import must never invent one.
        static::assertSame(0, $report->created());
        static::assertSame($hadKmf, $this->currency('KMF', $context) !== null);

        if (!$hadKmf) {
            static::assertGreaterThan(0, $report->missing());
        }
    }

    /**
     * Currency custom fields are translated: a marker set while editing in a
     * non-default language must still protect the record.
     */
    public function testOverrideSetInAnotherLanguageIsRespected(): void
    {
        $context = Context::createDefaultContext();

        $languages = $this->service('language.repository');
        static::assertInstanceOf(EntityRepository::class, $languages);
        $german = $languages->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('locale.code', 'de-DE'))->setLimit(1),
            $context,
        )->firstId();

        if ($german === null) {
            static::markTestSkipped('No de-DE language in the test database.');
        }

        $existing = $this->currency('XOF', $context);
        $id = $existing?->getId() ?? Uuid::randomHex();
        $this->currencyRepository()->upsert([[
            'id' => $id,
            'isoCode' => 'XOF',
            'name' => 'XOF',
            'shortName' => 'XOF',
            'symbol' => 'XOF',
            'factor' => 655.957,
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'translations' => [
                $german => ['name' => 'XOF', 'shortName' => 'XOF', 'customFields' => ['kmh_af_merchant_override' => true]],
            ],
        ]], $context);

        $importer = $this->service(ReferenceImporter::class);
        static::assertInstanceOf(ReferenceImporter::class, $importer);

        $report = $importer->import($context)['currencies'];

        static::assertGreaterThan(0, $report->overridden());
        static::assertSame(2, $this->currency('XOF', $context)?->getItemRounding()->getDecimals(), 'overridden XOF was corrected');
    }

    private function currency(string $iso, Context $context): ?CurrencyEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $iso))->setLimit(1);
        $currency = $this->currencyRepository()->search($criteria, $context)->first();

        return $currency instanceof CurrencyEntity ? $currency : null;
    }

    private function currencyRepository(): EntityRepository
    {
        $repository = $this->service('currency.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
