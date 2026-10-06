<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Geo;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Provenance;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataSet;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImportCommand;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\ImporterFactoryTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ReferenceImportCommand::class)]
#[UsesClass(ReferenceImporter::class)]
#[UsesClass(Reconciler::class)]
#[UsesClass(ReconcileAction::class)]
#[UsesClass(ReconciliationReport::class)]
#[UsesClass(ReferenceDataSet::class)]
#[UsesClass(Provenance::class)]
#[UsesClass(CurrencyRecord::class)]
#[UsesClass(CountryRecord::class)]
#[UsesClass(FeatureGate::class)]
#[UsesClass(Config::class)]
#[UsesClass(Feature::class)]
class ReferenceImportCommandTest extends TestCase
{
    use ImporterFactoryTrait;

    /** SymfonyStyle wraps to the terminal width; widen it so messages stay on one line. */
    protected function setUp(): void
    {
        putenv('COLUMNS=250');
    }

    protected function tearDown(): void
    {
        putenv('COLUMNS');
    }

    public function testDoesNothingWhenTheFeatureIsOff(): void
    {
        $gate = $this->gate(['referenceDataEnabled' => false]);
        $tester = new CommandTester(new ReferenceImportCommand($this->importer($gate), $gate));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertStringContainsString('Nothing imported', $tester->getDisplay());
    }

    public function testReportsMissingCurrenciesWithTheFix(): void
    {
        $gate = $this->gate(['referenceDataEnabled' => true]);
        $tester = new CommandTester(new ReferenceImportCommand($this->importer($gate), $gate));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        static::assertStringContainsString('Missing', $display);
        static::assertStringContainsString('Settings > Currencies', $display);
        static::assertStringContainsString('already converged', $display);
    }

    public function testReportsAReconcilingRunWithoutTheCurrencyNote(): void
    {
        $gate = $this->gate(['referenceDataEnabled' => true]);
        $importer = $this->importer($gate, withMissingCurrency: false, withNewCountry: true);
        $tester = new CommandTester(new ReferenceImportCommand($importer, $gate));

        $tester->execute([]);

        static::assertStringContainsString('Reference data reconciled.', $tester->getDisplay());
        static::assertStringNotContainsString('Settings > Currencies', $tester->getDisplay());
    }
}
