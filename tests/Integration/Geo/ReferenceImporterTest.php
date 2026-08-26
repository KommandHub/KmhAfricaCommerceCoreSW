<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Integration\Geo;

use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * In-stack proof of the reference importer. Requires a booted Shopware kernel and
 * database — runs under `make test` in the Docker stack, not in the framework-
 * agnostic library suite. Each test is wrapped in a rolled-back transaction by
 * IntegrationTestBehaviour, so it never leaves state behind.
 *
 * @group kernel
 */
class ReferenceImporterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    public function testSecondRunConvergesAndCorrectsCfaDecimals(): void
    {
        $importer = $this->getContainer()->get(ReferenceImporter::class);
        static::assertInstanceOf(ReferenceImporter::class, $importer);

        $context = Context::createDefaultContext();

        // First run may create/update; the second run must change nothing.
        $importer->import($context);
        $second = $importer->import($context);

        foreach ($second as $section => $report) {
            static::assertSame(0, $report->created(), "second run created {$section}");
            static::assertSame(0, $report->updated(), "second run updated {$section}");
            static::assertTrue($report->converged(), "{$section} did not converge");
        }

        // The flagship correction: CFA francs carry zero minor units (ISO 4217).
        $currencyRepository = $this->getContainer()->get('currency.repository');
        static::assertInstanceOf(EntityRepository::class, $currencyRepository);

        foreach (['XOF', 'XAF'] as $iso) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $iso))->setLimit(1);
            $currency = $currencyRepository->search($criteria, $context)->first();

            static::assertInstanceOf(CurrencyEntity::class, $currency, "{$iso} missing after import");
            static::assertSame(0, $currency->getItemRounding()->getDecimals(), "{$iso} itemRounding");
            static::assertSame(0, $currency->getTotalRounding()->getDecimals(), "{$iso} totalRounding");
        }
    }
}
