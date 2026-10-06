<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Administration\Controller;

use Kommandhub\AfricaCommerceCore\Administration\Controller\ReferenceImportController;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CountryRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\CurrencyRecord;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Provenance;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconcileAction;
use Kommandhub\AfricaCommerceCore\Domain\Geo\Reconciler;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReferenceDataSet;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Kommandhub\AfricaCommerceCore\Setting\Service\Config;
use Kommandhub\AfricaCommerceCore\Tests\Unit\Support\ImporterFactoryTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ReferenceImportController::class)]
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
class ReferenceImportControllerTest extends TestCase
{
    use ImporterFactoryTrait;

    /**
     * Same toggle as the CLI: off means a clear 409 the admin can explain.
     */
    public function testRefusesWhenTheFeatureIsOff(): void
    {
        $gate = $this->gate(['referenceDataEnabled' => false]);
        $response = (new ReferenceImportController($this->importer($gate), $gate))->import(Context::createDefaultContext());

        static::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        static::assertSame(ReferenceImportController::ERROR_DISABLED, $this->body($response->getContent())['errors'][0]['code']);
    }

    public function testReturnsTheCountsPerDataset(): void
    {
        $gate = $this->gate(['referenceDataEnabled' => true]);
        $response = (new ReferenceImportController($this->importer($gate), $gate))->import(Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $sections = $this->body($response->getContent())['sections'];
        static::assertSame(['currencies', 'countries', 'subdivisions', 'divisions'], array_keys($sections));
        static::assertSame(1, $sections['currencies']['missing']);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string|false $content): array
    {
        $body = json_decode((string)$content, true);
        static::assertIsArray($body);

        return $body;
    }
}
